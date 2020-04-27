<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\LoginAsCustomerUi\Controller\Login;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\LoginAsCustomer\Api\GetAuthenticationDataBySecretInterface;
use Magento\LoginAsCustomer\Api\AuthenticateCustomerInterface;
use Magento\LoginAsCustomer\Api\DeleteSecretInterface;

/**
 * Login As Customer storefront login action
 */
class Index implements HttpGetActionInterface
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var GetAuthenticationDataBySecretInterface
     */
    private $getAuthenticationDataBySecret;

    /**
     * @var AuthenticateCustomerInterface
     */
    private $authenticateCustomer;

    /**
     * @var DeleteSecretInterface
     */
    private $deleteSecretProcessor;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param CustomerRepositoryInterface $customerRepository
     * @param GetAuthenticationDataBySecretInterface $getAuthenticateDataProcessor
     * @param AuthenticateCustomerInterface $authenticateCustomerProcessor
     * @param DeleteSecretInterface $deleteSecretProcessor
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResultFactory $resultFactory,
        RequestInterface $request,
        CustomerRepositoryInterface $customerRepository,
        GetAuthenticationDataBySecretInterface $getAuthenticateDataProcessor,
        AuthenticateCustomerInterface $authenticateCustomerProcessor,
        DeleteSecretInterface $deleteSecretProcessor,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->request = $request;
        $this->customerRepository = $customerRepository;
        $this->getAuthenticationDataBySecret = $getAuthenticateDataProcessor;
        $this->authenticateCustomer = $authenticateCustomerProcessor;
        $this->deleteSecretProcessor = $deleteSecretProcessor;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * Login As Customer storefront login
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $secret = $this->request->getParam('secret');
            if (!$secret || !is_string($secret)) {
                throw new LocalizedException(__('Cannot login to account. No secret key provided.'));
            }

            $authenticateData = $this->getAuthenticationDataBySecret->execute($secret);

            $this->deleteSecretProcessor->execute($secret);

            try {
                $customer = $this->customerRepository->getById($authenticateData->getCustomerId());
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__('Customer are no longer exist.'));
            }

            $loggedIn = $this->authenticateCustomer->execute(
                $authenticateData->getCustomerId(),
                $authenticateData->getAdminId()
            );


            if (!$loggedIn) {
                throw new LocalizedException(__('Login was not successful.'));
            }


            $this->messageManager->addSuccessMessage(
                __('You are logged in as customer: %1', $customer->getFirstname() . ' ' . $customer->getLastname())
            );
            $resultRedirect->setPath('*/*/proceed');

        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('/');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            $this->messageManager->addErrorMessage(__('Cannot login to account.'));
            $resultRedirect->setPath('/');
        }
        return $resultRedirect;
    }
}
