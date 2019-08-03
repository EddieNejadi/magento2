<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\CustomerDownloadableProduct;

use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CustomerDownloadableProductTest extends GraphQlAbstract
{

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/Downloadable/_files/product_downloadable.php
     * @magentoApiDataFixture Magento/Downloadable/_files/order_with_downloadable_product.php
     */
    public function testGetCustomerDownloadableProducts()
    {
        $query = <<<MUTATION
mutation {
  generateCustomerToken(
    email: "customer@example.com"
    password: "password"
  ) {
    token
  }
}
MUTATION;
        $response = $this->graphQlMutation($query);
        $token = $response['generateCustomerToken']['token'];
        $this->headers = ['Authorization' => 'Bearer ' . $token];

        $query = <<<QUERY
        {
    customerDownloadableProducts{
        items{
            order_increment_id
            date
            status
            download_url
            remaining_downloads
        }
    }

}
QUERY;
        $objectManager = ObjectManager::getInstance();

        $searchCriteria = $objectManager->get(SearchCriteriaBuilder::class)->create();

        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
        $orders = $orderRepository->getList($searchCriteria)->getItems();
        $order = array_pop($orders);

        $searchCriteria = $objectManager->get(
            SearchCriteriaBuilder::class
        )->addFilter(
            'email',
            'customer@example.com'
        )->create();

        $customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
        $customers = $customerRepository->getList($searchCriteria)
            ->getItems();
        $customer = array_pop($customers);

        $order->setCustomerId($customer->getId())->setCustomerIsGuest(false)->save();
        $response = $this->graphQlQuery($query, [], '', $this->headers);

        $this->assertEquals(
            $order->getIncrementId(),
            $response['customerDownloadableProducts']['items'][0]['order_increment_id']
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testGetCustomerDownloadableProductsIfProductsDoNotExist()
    {
        $query = <<<MUTATION
mutation {
  generateCustomerToken(
    email: "customer@example.com"
    password: "password"
  ) {
    token
  }
}
MUTATION;
        $response = $this->graphQlMutation($query);
        $token = $response['generateCustomerToken']['token'];
        $this->headers = ['Authorization' => 'Bearer ' . $token];

        $query = <<<QUERY
        {
    customerDownloadableProducts{
        items{
            order_increment_id
            date
            status
            download_url
            remaining_downloads
        }
    }

}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $this->headers);
        $this->assertEmpty($response['customerDownloadableProducts']['items']);
    }


    public function testGuestCannotAccessDownloadableProducts()
    {
        $query = <<<QUERY
        {
    customerDownloadableProducts{
        items{
            order_increment_id
            date
            status
            download_url
            remaining_downloads
        }
    }

}
QUERY;

        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('GraphQL response contains errors: The current customer isn\'t authorized');
        $this->graphQlQuery($query);
    }
}
