<?php

namespace Evp\Bundle\TicketBundle\Service;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Evp\Bundle\DeviceApiBundle\Services\ApiTicketManager;
use Evp\Bundle\PassbookBundle\Service\PassbookTicketManager;
use Evp\Bundle\PaymentBundle\PaymentHandler\HandlerInterface;
use Evp\Bundle\TicketAdminBundle\Entity\User as Cashier;
use Evp\Bundle\TicketBundle\Entity\Event;
use Evp\Bundle\TicketBundle\Entity\Order;
use Evp\Bundle\TicketBundle\Entity\OrderConfirmationResult;
use Evp\Bundle\TicketBundle\Entity\PriceType;
use Evp\Bundle\TicketBundle\Entity\Seat\Seat;
use Evp\Bundle\TicketBundle\Entity\Step\OrderDetails;
use Evp\Bundle\TicketBundle\Entity\Step\OrderModificationResult;
use Evp\Bundle\TicketBundle\Entity\Step\TicketCountAvailabilityResult;
use Evp\Bundle\TicketBundle\Entity\Ticket;
use Evp\Bundle\TicketBundle\Entity\TicketType;
use Evp\Bundle\TicketBundle\Entity\User;
use Evp\Bundle\TicketBundle\Exception\InvalidTicketCountException;
use Evp\Bundle\TicketBundle\Repository\EventRepository;
use Evp\Bundle\TicketBundle\Repository\OrderDetailsRepository;
use Evp\Bundle\TicketBundle\Repository\OrderRepository;
use Evp\Bundle\TicketBundle\Repository\PriceTypeRepository;
use Evp\Bundle\TicketBundle\Repository\TicketRepository;
use Evp\Bundle\TicketBundle\Repository\TicketTypeRepository;
use Evp\Bundle\TicketManufacturingBundle\Service\Publisher\InvoiceFinalManufacturingPublisher;
use Evp\Bundle\TicketManufacturingBundle\Service\Publisher\TicketManufacturingPublisher;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class OrderManager
{
    private $eventSessionKey;
    private $session;
    private $ticketTypeRepository;
    private $orderDetailsRepository;
    private $orderRepository;
    private $eventRepository;
    private $invoiceDetailsManager;
    private $priceTypeRepository;
    private $ticketRepository;
    private $ticketManufacturingPublisher;
    private $invoiceFinalManufacturingPublisher;
    private $userSession;
    private $passbookTicketManager;
    private $maxTicketsPerUserProvider;
    private $apiTicketManager;
    private $entityManager;
    private $logger;

    /**
     * @param EntityManager $entityManager
     * @param LoggerInterface $logger
     * @param string $eventSessionKey
     * @param \Symfony\Component\HttpFoundation\Session\Session $session
     * @param TicketTypeRepository $ticketTypeRepository
     * @param OrderDetailsRepository $orderDetailsRepository
     * @param OrderRepository $orderRepository
     * @param EventRepository $eventRepository
     * @param InvoiceDetailsManager $invoiceDetailsManager
     * @param PriceTypeRepository $priceTypeRepository
     * @param TicketRepository $ticketRepository
     * @param TicketManufacturingPublisher $ticketManufacturingPublisher
     * @param InvoiceFinalManufacturingPublisher $invoiceFinalManufacturingPublisher
     * @param UserSession $userSession
     * @param PassbookTicketManager $passbookTicketManager
     * @param MaxTicketsPerUserProvider $maxTicketsPerUserProvider
     * @param ApiTicketManager $apiTicketManager
     */
    public function __construct(
        EntityManager $entityManager,
        LoggerInterface $logger,
        $eventSessionKey,
        Session $session,
        TicketTypeRepository $ticketTypeRepository,
        OrderDetailsRepository $orderDetailsRepository,
        OrderRepository $orderRepository,
        EventRepository $eventRepository,
        InvoiceDetailsManager $invoiceDetailsManager,
        PriceTypeRepository $priceTypeRepository,
        TicketRepository $ticketRepository,
        TicketManufacturingPublisher $ticketManufacturingPublisher,
        InvoiceFinalManufacturingPublisher $invoiceFinalManufacturingPublisher,
        UserSession $userSession,
        PassbookTicketManager $passbookTicketManager,
        MaxTicketsPerUserProvider $maxTicketsPerUserProvider,
        ApiTicketManager $apiTicketManager
    ) {
        $this->eventSessionKey = $eventSessionKey;
        $this->session = $session;
        $this->ticketTypeRepository = $ticketTypeRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->orderDetailsRepository = $orderDetailsRepository;
        $this->orderRepository = $orderRepository;
        $this->eventRepository = $eventRepository;
        $this->invoiceDetailsManager = $invoiceDetailsManager;
        $this->priceTypeRepository = $priceTypeRepository;
        $this->ticketRepository = $ticketRepository;
        $this->ticketManufacturingPublisher = $ticketManufacturingPublisher;
        $this->invoiceFinalManufacturingPublisher = $invoiceFinalManufacturingPublisher;
        $this->userSession = $userSession;
        $this->passbookTicketManager = $passbookTicketManager;
        $this->maxTicketsPerUserProvider = $maxTicketsPerUserProvider;
        $this->apiTicketManager = $apiTicketManager;
    }

    /**
     * @param Cashier $cashier
     *
     * @return Order|null
     */
    public function getCurrentOrderForCashier(Cashier $cashier)
    {
        return $this->orderRepository->getOneValidByCashier($cashier);
    }

    public function getCurrentOrderForCurrentCashier()
    {
        return $this->getCurrentOrderForCashier($this->userSession->getCashier());
    }

    /**
     * @param Cashier $cashier
     * @param Event   $event
     *
     * @return Order
     */
    public function createNewOrderForCashier(Cashier $cashier, Event $event)
    {
        $user = new User();
        $user
            ->setDateCreated(new \DateTime())
            ->setLocale($this->userSession->getCurrentLocale())
        ;
        $order = new Order();
        $order
            ->setCashier($cashier)
            ->setPaymentType(HandlerInterface::PAYMENT_TYPE_GENERATED_MANUALLY)
            ->setStatus(Order::STATUS_IN_PROGRESS)
            ->setStateExpires($this->getCashierOrderExpirationDate($event))
            ->setUser($user)
            ->setEvent($event)
            ->setTestMode($event->getSettings()->getTestMode())
        ;

        $this->entityManager->persist($user);
        $this->entityManager->persist($order);

        return $order;
    }

    public function createNewOrderForCurrentCashier(Event $event)
    {
        return $this->createNewOrderForCashier($this->userSession->getCashier(), $event);
    }

    /**
     * Creates new Order for current User
     *
     * @param User  $user
     * @param Event $event
     *
     * @return Order
     */
    public function createFromUser(User $user, Event $event)
    {
        $order = Order::create()
            ->setUser($user)
            ->setStatus(Order::STATUS_IN_PROGRESS)
            ->setStateExpires($this->getStepExpirationDate($event))
            ->setEvent($event)
        ;
        $this->entityManager->persist($order);
        $user->setOrder($order);

        return $order;
    }

    /**
     * Removes OrderDetails & Tickets by provided User, Event, OrderDetails
     *
     * @param \Evp\Bundle\TicketBundle\Entity\User $user
     * @param \Evp\Bundle\TicketBundle\Entity\Event $event
     * @param \Evp\Bundle\TicketBundle\Entity\Step\OrderDetails $orderDetails
     */
    public function removeOrderDetailsAndTickets(User $user, Event $event, OrderDetails $orderDetails) {
        $tickets = $this->ticketRepository->findBy(array(
            'user' => $user,
            'event' => $event,
            'orderDetails' => $orderDetails,
        ));
        foreach ($tickets as $ticket) {
            $this->entityManager->remove($ticket);
        }
        $this->entityManager->remove($orderDetails);
    }

    /**
     * Extends the order reservation time for a little bit more
     *
     * @param Order $order
     */
    public function extendShortTermReservationTime(Order $order) {
        if (!$this->hasOrderExpired($order)) {
            $order->setStateExpires($this->getStepExpirationDate($order->getEvent()));
        }
    }

    /**
     * Updates and persists TicketCount & Price fields in Order Entity
     *
     * @param Order $order
     */
    public function updateTicketCountAndPrice(Order $order)
    {
        $order->setOrderPrice($this->computeTotalPrice($order));
        $order->setTicketsCount($this->ticketRepository->countAllByOrder($order));
    }

    /**
     * Determines if the order is full of free tickets and does not require payment
     *
     * @param Order $order
     * @return bool
     */
    public function isOrderFreeOfCharge(Order $order)
    {
        $totalPrice = $this->computeTotalPrice($order);
        return floatval($totalPrice) === floatval(0);
    }

    /**
     * Compute the total price
     *
     * @param Order $order
     * @return float
     */
    public function computeTotalPrice(Order $order)
    {
        $ticketsBought = $this->ticketRepository->getAllByOrder($order);

        $totalPrice = 0;
        foreach ($ticketsBought as $ticket) {
            $totalPrice += $ticket->getPrice();
        }

        return $totalPrice;
    }

    /**
     * Extends the order reservation time (longer than short-term reservation)
     * This is done once the user is redirected to the payment system
     *
     * @param Order $order
     */
    public function extendLongTermReservationTime(Order $order) {
        if (!$this->hasOrderExpired($order)) {
            $order->setStateExpires($this->getPaymentExpirationDate($order->getEvent()));
        }
    }

    /**
     * Checks the order expiration date
     *
     * @param Order $order
     * @return bool
     */
    public function hasOrderExpired(Order $order)
    {
        return $order->getStateExpires() <= new \DateTime();
    }

    /**
     * @param Order $order
     * @return OrderConfirmationResult
     */
    public function attemptConfirmExpiredOrder(Order $order)
    {
        $event = $order->getEvent();
        $result = new OrderConfirmationResult();

        if ($event->getSeatsEnabled()) {
            return $result
                ->setCanConfirm(false)
                ->setMessage(OrderConfirmationResult::MESSAGE_NO_SEATS)
                ;
        }

        $remainingTickets = [];
        foreach ($order->getUser()->getTickets() as $ticket) {
            $ticketType = $ticket->getTicketType();
            if (!$ticketType->getStatus() || $ticketType->isOnlyForCashier()) {
                return $result
                    ->setCanConfirm(false)
                    ->setMessage(OrderConfirmationResult::MESSAGE_TICKET_TYPE_NOT_AVAILABLE)
                    ;
            }
            if ($ticket->getPrice() !== $ticket->getPriceType()->getPrice()) {
                return $result
                    ->setCanConfirm(false)
                    ->setMessage(OrderConfirmationResult::MESSAGE_PRICE_CHANGED)
                    ;
            }
            if (!isset($remainingTickets[$ticketType->getId()])) {
                $count = $this->ticketTypeRepository->getAvailableCountByTicketType($ticketType);
                $remainingTickets[$ticketType->getId()] = $count;
            }
            if ($remainingTickets[$ticketType->getId()] !== null) {
                $remainingTickets[$ticketType->getId()]--;
            }
        }

        foreach (array_filter($remainingTickets) as $item) {
            if ($item < 0) {
                return $result
                    ->setCanConfirm(false)
                    ->setMessage(OrderConfirmationResult::MESSAGE_COUNT_NOT_AVAILABLE)
                    ;
            }
        }

        return $result;
    }

    /**
     * Validates Order for Invoice printing by given token
     *
     * @param Order $order
     * @return bool
     */
    public function isOrderValidForInvoice(Order $order = null) {
        if ($order === null) {
            return false;
        }
        if (
            $order->getStatus() !== Order::STATUS_AWAITING_PAYMENT
            && $order->getStatus() !== Order::STATUS_DONE
        ) {
            return false;
        }
        if ($order->getInvoice() === null) {
            return false;
        }
        return true;
    }

    /**
     * Checks if the order is done or not
     *
     * @param Order $order
     * @return bool
     */
    public function isOrderDone(Order $order)
    {
        return $order->getStatus() === Order::STATUS_DONE;
    }

    /**
     * @param Order $order
     * @return self
     */
    public function updateTicketStatus(Order $order)
    {
        $orderTickets = $this->ticketRepository->getAllByOrder($order);

        foreach ($orderTickets as $ticket) {
            $ticket->setStatus(Ticket::STATUS_UNUSED);
            $this->apiTicketManager->processUnusedTicket($ticket);
        }
        $this->updateOrderDiscountAmount($order);
        return $this;
    }

    /**
     * @param Order $order
     */
    public function updateOrderPriceAndCount(Order $order)
    {
        $price = 0;
        $count = 0;
        foreach ($order->getOrderDetails() as $detail) {
            if ($detail->getPriceType() !== null) {
                $price += $detail->getPriceType()->getPrice() * $detail->getTicketsCount();
            }
            $count += $detail->getTicketsCount();
        }

        $order
            ->setOrderPrice($price)
            ->setTicketsCount($count)
        ;
    }

    /**
     * @param Order $order
     */
    public function updateOrderDiscountAmount(Order $order)
    {
        $discountedPrice = 0;
        foreach ($order->getUser()->getTickets() as $ticket) {
            $discountedPrice += $ticket->getPrice();
        }
        $discountAmount = $order->getOrderPrice() - $discountedPrice;
        if ($discountAmount > 0) {
            $order->setDiscountAmount($discountAmount);
        }
    }

    /**
     * Updates Seat status
     *
     * @param Order $order
     * @return self
     */
    public function updateSeatStatus(Order $order)
    {
        $orderTickets = $this->ticketRepository->getAllByOrder($order);

        foreach ($orderTickets as $ticket) {
            $seat = $ticket->getSeat();
            if (!empty($seat)) {
                $seat->setStatus(Seat::STATUS_TAKEN);
            }
        }
        return $this;
    }

    /**
     * @param Order $order
     *
     * @return $this
     */
    public function updateInvoice(Order $order)
    {
        if ($order->getInvoiceRequired()) {
            $this->invoiceDetailsManager->updateOrderInvoice($order);
        }

        return $this;
    }

    /**
     * Modifies or creates new OrderDetail
     *
     * @param TicketType $ticketType
     * @param PriceType  $priceType
     * @param int        $countForTicketType
     * @param User       $user
     *
     * @return OrderModificationResult
     */
    public function modifyOrderDetailsCountForTicketType(
        TicketType $ticketType,
        PriceType $priceType,
        $countForTicketType,
        User $user
    ) {
        $orderDetailsRepository = $this->entityManager->getRepository('Evp\Bundle\TicketBundle\Entity\Step\OrderDetails');
        $currentOrderDetail = $orderDetailsRepository->findOneBy(array(
            'ticketType' => $ticketType,
            'user' => $user,
        ));
        $totalDetails = $orderDetailsRepository->getAllByUserAndEvent($user, $ticketType->getEvent());

        $currentlyExistingCount = 0;
        foreach ($totalDetails as $totalDetail) {
            if ($currentOrderDetail !== null) {
                if ($totalDetail->getId() !== $currentOrderDetail->getId()) {
                    $currentlyExistingCount += $totalDetail->getTicketsCount();
                }
            } else {
                $currentlyExistingCount += $totalDetail->getTicketsCount();
            }
        }

        $result = new OrderModificationResult();

        $availabilityResult = $this->calculateTicketCountAvailabilityResult(
            $ticketType,
            $currentlyExistingCount,
            $countForTicketType,
            $currentOrderDetail
        );
        if ($availabilityResult->isAvailable()) {
            if ($currentOrderDetail === null) {
                return $result->setOrderDetails(
                    $this->createNewOrderDetail($user, $ticketType, $priceType, $countForTicketType)
                );
            }
            return $result->setOrderDetails(
                $currentOrderDetail
                    ->setTicketsCount($countForTicketType)
                    ->setPriceType($priceType)
                    ->setOriginalPrice($priceType->getPrice())
            );
        }

        if ($availabilityResult->getTicketType() === null) {
            return $result->setMaxAvailableTickets(0);
        }

        return $result->setMaxAvailableTickets($availabilityResult->getAvailableCount());
    }

    /**
     * @param Order $order
     * @return self
     */
    public function updateOrderStatus(Order $order)
    {
        $order->setStatus(Order::STATUS_DONE);
        $order->setDateFinished(new \DateTime());
        $order->setTestMode($order->getEvent()->getSettings()->getTestMode());

        return $this;
    }

    /**
     * @param User $user
     * @param int $ticketTypeId
     * @param int $priceTypeId
     * @param int $count
     *
     * @return OrderDetails
     *
     * @throws InvalidTicketCountException
     */
    public function createOrderDetailsFromWidgetData(User $user, $ticketTypeId, $priceTypeId, $count)
    {
        /** @var TicketType $ticketType */
        $ticketType = $this->ticketTypeRepository->find($ticketTypeId);
        /** @var PriceType $priceType */
        $priceType = $this->priceTypeRepository->find($priceTypeId);

        if ($ticketType === null || $priceType === null || empty($count)) {
            throw new \InvalidArgumentException();
        }

        $availableCount = $this->maxTicketsPerUserProvider->getMaxTicketsPerUser($ticketType, $user);
        if ($count > $availableCount) {
            throw new InvalidTicketCountException();
        }

        $result = $this->modifyOrderDetailsCountForTicketType($ticketType, $priceType, $count, $user);
        if ($result->getOrderDetails() === null) {
            throw new InvalidTicketCountException();
        }

        return $result->getOrderDetails();
    }

    /**
     * @param User       $user
     * @param TicketType $ticketType
     * @param PriceType  $priceType
     * @param int        $count
     *
     * @return OrderDetails
     */
    private function createNewOrderDetail(User $user, TicketType $ticketType, PriceType $priceType, $count)
    {
        $orderDetail = new OrderDetails();
        $orderDetail
            ->setOrder($user->getOrder())
            ->setEvent($ticketType->getEvent())
            ->setTicketsCount($count)
            ->setUser($user)
            ->setTicketType($ticketType)
            ->setPriceType($priceType)
            ->setOriginalPrice($priceType->getPrice())
        ;

        $this->entityManager->persist($orderDetail);
        return $orderDetail;
    }

    /**
     * @param TicketType $ticketType
     * @param int $currentlyExistingCount
     * @param int $countForTicketType
     * @param OrderDetails|null $currentOrderDetail
     *
     * @return TicketCountAvailabilityResult
     */
    public function calculateTicketCountAvailabilityResult(
        TicketType $ticketType,
        $currentlyExistingCount,
        $countForTicketType,
        OrderDetails $currentOrderDetail = null
    ) {
        $freeTickets = $this->ticketTypeRepository->getAvailableCountByTicketType($ticketType);
        $maxAllowed = $ticketType->getEvent()->getMaxTicketsPerUser();

        $availabilityResult = new TicketCountAvailabilityResult();
        if (($currentlyExistingCount + $countForTicketType) > $maxAllowed) {
            $availabilityResult
                ->setAvailableCount($maxAllowed)
                ->setAvailable(false)
            ;
            return $availabilityResult;
        }

        elseif ($ticketType->getMaxTicketsPerUser() !== null) {
            if ($countForTicketType > $ticketType->getMaxTicketsPerUser()) {
                $availabilityResult
                    ->setAvailableCount($ticketType->getMaxTicketsPerUser())
                    ->setAvailable(false)
                ;
                return $availabilityResult;
            }
        }

        if ($freeTickets !== null) {
            $availabilityResult
                ->setTicketType($ticketType)
                ->setAvailableCount($freeTickets)
            ;
            if ($currentOrderDetail !== null) {
                if (($countForTicketType - $currentOrderDetail->getTicketsCount()) > $freeTickets) {
                    return $availabilityResult->setAvailable(false);
                } else {
                    return $availabilityResult->setAvailable(true);
                }
            } else {
                if ($countForTicketType > $maxAllowed) {
                    return $availabilityResult->setAvailable(false);
                } else {
                    return $availabilityResult->setAvailable(true);
                }
            }
        }

        return $availabilityResult->setAvailable(true);
    }

    /**
     * @param int $orderId
     * @param bool $lock
     * @return Order
     */
    public function getOrderById($orderId, $lock = false)
    {
        return $this->orderRepository->find($orderId, $lock ? LockMode::PESSIMISTIC_WRITE : null);
    }

    /**
     * @param string $orderToken
     * @param bool $lock
     *
     * @return Order
     */
    public function getOrderByToken($orderToken, $lock = false)
    {
        return $this->orderRepository->findOneByToken($orderToken, $lock ? LockMode::PESSIMISTIC_WRITE : null);
    }

    /**
     * @param Order $order
     * @return OrderDetails[]
     */
    public function getOrderDetailsForOrder($order)
    {
        return $order->getOrderDetails();
    }

    /**
     * @param Event $event
     *
     * @return \DateTime
     */
    public function getCashierOrderExpirationDate(Event $event)
    {
        $now = new \DateTime();
        return $now->add($event->getSettings()->getCashierReservationDateInterval());
    }

    /**
     * @param Event $event
     *
     * @return \DateTime
     */
    private function getStepExpirationDate(Event $event)
    {
        $now = new \DateTime();
        return $now->add($event->getSettings()->getStepReservationDateInterval());
    }

    /**
     * @param Event $event
     *
     * @return \DateTime
     */
    private function getPaymentExpirationDate(Event $event)
    {
        $now = new \DateTime();
        return $now->add($event->getSettings()->getPaymentAwaitDateInterval());
    }

    /**
     * Confirms the order and updates all of the needed fields
     *
     * @param Order $order
     */
    public function confirmOrder(Order $order)
    {
        $this->updateTicketStatus($order);
        $this->updateOrderStatus($order);
        $this->updateSeatStatus($order);
        $this->updateInvoice($order);
        $this->passbookTicketManager->generatePassbookTickets($order);
    }

    public function cancelOrder(Order $order)
    {
        $order
            ->setStatus(Order::STATUS_CANCELED)
            ->setStateExpires(new \DateTime())
        ;

        $orderTickets = $this->ticketRepository->getAllByOrder($order);
        foreach ($orderTickets as $ticket) {
            if ($ticket->getSeat() !== null) {
                $ticket->getSeat()
                    ->setStatus(Seat::STATUS_FREE)
                    ->setOrderDetails(null)
                ;
            }

            $ticket
                ->setStatus(Ticket::STATUS_CANCELED)
                ->setDateModified(new \DateTime())
                ->setSeat(null)
            ;

            $this->apiTicketManager->processUsedTicket($ticket);
        }
    }

    /**
     * Publishes an order to the ticket/invoice generation queue
     *
     * @param Order $order
     */
    public function publishOrderToQueue(Order $order)
    {
        $this->ticketManufacturingPublisher->publishOrder($order->getId());

        if ($this->isOrderValidForInvoice($order)) {
            $this->invoiceFinalManufacturingPublisher->publishOrder($order->getId());
        }
    }
}
