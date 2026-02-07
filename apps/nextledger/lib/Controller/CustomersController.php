<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Customer;
use OCA\NextLedger\Db\CustomerMapper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class CustomersController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private CustomerMapper $customerMapper,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(): JSONResponse {
        $items = $this->customerMapper->findAll();
        $data = array_map(fn(Customer $customer) => $this->entityToArray($customer), $items);

        usort($data, static fn(array $a, array $b) => strcasecmp($a['company'] ?? '', $b['company'] ?? ''));

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        ?string $company = null,
        ?string $contactName = null,
        ?string $street = null,
        ?string $houseNumber = null,
        ?string $zip = null,
        ?string $city = null,
        ?string $email = null,
    ): JSONResponse {
        $customer = new Customer();
        $customer->setCompany($company);
        $customer->setContactName($contactName);
        $customer->setStreet($street);
        $customer->setHouseNumber($houseNumber);
        $customer->setZip($zip);
        $customer->setCity($city);
        $customer->setEmail($email);
        $customer->setCreatedAt(time());
        $customer->setUpdatedAt(time());

        $saved = $this->customerMapper->insert($customer);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?string $company = null,
        ?string $contactName = null,
        ?string $street = null,
        ?string $houseNumber = null,
        ?string $zip = null,
        ?string $city = null,
        ?string $email = null,
    ): JSONResponse {
        $customerId = (int)$id;
        try {
            /** @var Customer $customer */
            $customer = $this->customerMapper->find($customerId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Kunde nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $customer->setCompany($company);
        $customer->setContactName($contactName);
        $customer->setStreet($street);
        $customer->setHouseNumber($houseNumber);
        $customer->setZip($zip);
        $customer->setCity($city);
        $customer->setEmail($email);
        $customer->setUpdatedAt(time());

        $saved = $this->customerMapper->update($customer);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $customerId = (int)$id;
        try {
            /** @var Customer $customer */
            $customer = $this->customerMapper->find($customerId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Kunde nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->customerMapper->delete($customer);
        return new JSONResponse(['status' => 'ok']);
    }

    private function entityToArray(object $entity): array {
        if (method_exists($entity, 'jsonSerialize')) {
            /** @var array $data */
            $data = $entity->jsonSerialize();
            return $data;
        }

        return get_object_vars($entity);
    }
}
