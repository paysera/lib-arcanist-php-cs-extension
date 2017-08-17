<?php

namespace Evp\Bundle\PaymentBundle\PaymentHandler;

use Doctrine\ORM\EntityManager;
use Evp\Bundle\PaymentBundle\Entity\PaymentCallback;
use Evp\Bundle\PaymentBundle\Entity\PaymentType;
use Evp\Bundle\PaymentBundle\PaymentHandler\Exception\OrderIntegrityException;
use Evp\Bundle\PaymentBundle\PaymentHandler\Exception\OrderStatusException;
use Evp\Bundle\PaymentBundle\PaymentHandler\Exception\WebToPayHandlerException;
use Evp\Bundle\TicketBundle\Entity\Event;
use Evp\Bundle\TicketBundle\Entity\Order;
use Evp\Bundle\TicketBundle\Entity\User;
use Evp\Bundle\TicketBundle\Service\UserSession;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;
use WebToPay_Factory;

class WebToPayHandler extends HandlerAbstract
{
    const CALLBACK_STATUS_PAYMENT_CONFIRMED = 1;
    const CALLBACK_STATUS_ADDITIONAL_DATA = 3;

    private $languageMapper;
    private $defaultPaymentCountry;
    private $fallbackLanguage;
    private $translator;

    /**
     * @param Router $router
     * @param UserSession $userSession
     * @param LoggerInterface $logger
     * @param EntityManager $entityManager
     * @param WebToPayLanguageMapper $languageMapper
     * @param string $defaultPaymentCountry
     * @param string $fallbackLanguage
     * @param TranslatorInterface $translator
     */
    function __construct(
        Router $router,
        UserSession $userSession,
        LoggerInterface $logger,
        EntityManager $entityManager,
        WebToPayLanguageMapper $languageMapper,
        $defaultPaymentCountry,
        $fallbackLanguage,
        TranslatorInterface $translator
    ) {
        parent::__construct(
            $router,
            $userSession,
            $logger,
            $entityManager
        );

        $this->languageMapper = $languageMapper;
        $this->defaultPaymentCountry = $defaultPaymentCountry;
        $this->fallbackLanguage = $fallbackLanguage;
        $this->translator = $translator;
    }

    public function getPaymentTypesForUser(User $user)
    {
        $this->getLogger()->debug(__METHOD__);

        $order = $this->getOrderForUser($user);
        $event = $order->getEvent();

        $this->getLogger()->debug(
            'Got event and order',
            [
                'event' => $event,
                'order' => $order,
            ]
        );

        $paymentMethods = $this->getWebtopayFactory($user->getOrder())
            ->getPaymentMethodListProvider()
            ->getPaymentMethodList($event->getCurrency())
            ->setDefaultLanguage($user->getLocale())
        ;

        if ($order->getOrderPrice() === null) {
            throw new Exception('Order price was not set on the last step');
        }

        $paymentGroups = [];
        $paymentCountries = $event->getSettings()->getPaymentCountries();
        if (empty($paymentCountries)) {
            $paymentCountries[] = $this->defaultPaymentCountry;
        }
        foreach ($event->getSettings()->getPaymentCountries() as $countryCode) {
            $paymentMethodsForCountry = $paymentMethods->getCountry($countryCode);
            $paymentGroups[$countryCode] = $paymentMethodsForCountry->filterForAmount(
                $order->getOrderPrice() * 100,
                $event->getCurrency()
            )->getGroups();
        }

        return $this->mapGroupsToPaymentTypes($paymentGroups);
    }

    public function getPaymentResponseForUser(User $user, PaymentType $paymentType, $locale)
    {

        $request = $this->getWebtopayFactory($user->getOrder())
            ->getRequestBuilder()
            ->buildRequest(
                [
                    'lang' => $this->languageMapper->convertToWebToPayLanguage($locale),
                    'p_email' => $user->getEmail(),
                    'orderid' => $user->getOrder()->getId(),
                    'amount' => $this->calculateOrderFinalPrice($user->getOrder()),
                    'currency' => $user->getOrder()->getEvent()->getCurrency(),
                    'country'  => $user->getOrder()->getEvent()->getCountryCode(),
                    'accepturl' => $this->getAcceptUrlForUser($user, $locale),
                    'cancelurl' => $this->getCancelUrl($locale),
                    'callbackurl' => $this->getCallbackUrl($user->getOrder()),
                    'payment' => $paymentType->getName(),
                    'test' => $user->getOrder()->getEvent()->getSettings()->getTestMode(),
                    'paytext' => $this->buildPayText($user->getOrder()),
                ]
            );

        return new RedirectResponse(\WebToPay::PAYSERA_PAY_URL . '?' . http_build_query($request));
    }

    public function handleCallback(Order $order, $request)
    {
        $requestParameters = $request->request->count() !== 0 ? $request->request->all() : $request->query->all();
        $parsedRequestData = [];

        $callback = PaymentCallback::create()
            ->setHandlerName($this->getName())
            ->setRawRequestData($requestParameters)
        ;

        $this->getEntityManager()->persist($callback);
        $this->getLogger()->debug('Request parameters', $requestParameters);

        try {
            $callbackValidator = $this->getWebtopayFactory($order)->getCallbackValidator();
            $parsedRequestData = $callbackValidator->validateAndParseData($requestParameters);

            $callback->setParsedRequestData($parsedRequestData);
            $callbackStatus = (int)$parsedRequestData['status'];

            if ($callbackStatus === self::CALLBACK_STATUS_PAYMENT_CONFIRMED) {
                $callback->setOrder($order);
                $this->validateMoneyReceived($order, $parsedRequestData);
                $this->getEntityManager()->persist($callback);
                return HandlerInterface::RESULT_SUCCESS;
            }
            if ($callbackStatus === self::CALLBACK_STATUS_ADDITIONAL_DATA) {
                return HandlerInterface::RESULT_SKIP;
            }
            throw new OrderStatusException('Unexpected payment status');
        } catch (WebToPayHandlerException $webToPayHandlerException) {
            $this->getLogger()->debug($webToPayHandlerException);
            $this->getLogger()->error('Could not handle callback, error occurred', $parsedRequestData);
            $callback->setErrorMessage($webToPayHandlerException->getMessage());
            $this->getEntityManager()->flush();

            return HandlerInterface::RESULT_FAILURE;
        }
    }

    public function getOkResponse()
    {
        return new Response('OK', 200, ['content-type' => 'text/html']);
    }

    public function getErrorResponse($message = 'Error', $status = 400)
    {
        return new Response($message, $status, ['content-type' => 'text/html']);
    }

    public function getAvailablePaymentTypes(Event $event)
    {
        $webtopayFactory = new WebToPay_Factory(['projectId' => $event->getSettings()->getProjectId()]);
        $countries = $webtopayFactory->getPaymentMethodListProvider()
            ->getPaymentMethodList($event->getCurrency())
            ->getCountries()
        ;

        $list = [];
        foreach ($countries as $country) {
            foreach ($country->getPaymentMethods() as $paymentMethod) {
                $list[$paymentMethod->getTitle($this->userSession->getCurrentLocale())] = $paymentMethod->getKey();
            }
        }

        return $list;
    }

    private function calculateOrderFinalPrice(Order $order)
    {
        $discountedPrice = $order->getOrderPrice() - $order->getDiscountAmount();
        if ($discountedPrice <= 0) {
            $discountedPrice = 0.01;
        }

        return $discountedPrice * 100;
    }

    /**
     * @param \WebToPay_PaymentMethodGroup[][] $paymentGroups
     * @return array
     */
    private function mapGroupsToPaymentTypes($paymentGroups)
    {
        $paymentTypes = [];

        foreach ($paymentGroups as $country => $groups) {
            foreach ($groups as $group) {
                foreach ($group->getPaymentMethods() as $paymentMethod) {
                    $paymentMethod->setDefaultLanguage($this->fallbackLanguage);
                    $paymentTypes[$country][$paymentMethod->getKey()] = PaymentType::create()
                        ->setName($paymentMethod->getKey())
                        ->setTitle($paymentMethod->getTitle($country))
                        ->setLogoUrl($paymentMethod->getLogoUrl($country))
                        ->setHandlerClass($this->getName())
                        ->setCountry($country)
                    ;
                }
            }
        }
        return $paymentTypes;
    }

    /**
     * @param Order $order
     *
     * @return string
     */
    private function getCallbackUrl(Order $order)
    {
        return $this->getRouter()->generate(
            'handle_callback',
            [
                'orderToken' => $order->getToken(),
                'paymentHandlerName' => $this->getName(),
            ],
            true
        );
    }

    /**
     * @param Order $order
     * @param array $parsedRequestData
     * @throws OrderIntegrityException
     */
    private function validateMoneyReceived(Order $order, $parsedRequestData)
    {
        $requestAmount = number_format($parsedRequestData['amount'] / 100, 2);
        $requestCurrency = $parsedRequestData['currency'];

        $orderPrice = $this->calculateOrderFinalPrice($order) / 100;
        $isAmountDifferent = number_format($orderPrice, 2) !== $requestAmount;
        $isCurrencyDifferent = $order->getEvent()->getCurrency() !== $requestCurrency;

        if ($isAmountDifferent || $isCurrencyDifferent) {
            $this->getLogger()->debug('Order amount or currency mismatch',
                [
                    'expected' => $orderPrice,
                    'received' => $requestAmount,
                ]
            );
            throw new OrderIntegrityException('Order amount or currency mismatch');
        }
    }

    /**
     * @param User $user
     * @param string $locale
     * @return string
     */
    private function getAcceptUrlForUser(User $user, $locale)
    {
        return $this->getRouter()->generate(
            'payment_completed',
            [
                'orderToken' => $user->getOrder()->getToken(),
                '_locale' => $locale,
            ],
            true
        );
    }

    /**
     * @param string $locale
     * @return string
     */
    private function getCancelUrl($locale)
    {
        return $this->getRouter()->generate('payment_cancelled', ['_locale' => $locale], true);
    }

    private function buildPayText(Order $order)
    {
        return $this->translator->trans(
            'payment.pay_text',
            [
                '%event%' => $order->getEvent()->getName(),
            ],
            'TicketFrontend'
        );
    }

    private function getWebtopayFactory(Order $order)
    {
        $settings = $order->getEvent()->getSettings();

        return new \WebToPay_Factory([
            'projectId' => $settings->getProjectId(),
            'password' => $settings->getProjectSign(),
        ]);
    }
}
