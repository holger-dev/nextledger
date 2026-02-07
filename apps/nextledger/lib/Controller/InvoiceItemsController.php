<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\InvoiceItem;
use OCA\NextLedger\Db\InvoiceItemMapper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class InvoiceItemsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private InvoiceItemMapper $invoiceItemMapper,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(string $invoiceId): JSONResponse {
        $invoiceKey = (int)$invoiceId;
        $items = $this->invoiceItemMapper->findByInvoiceId($invoiceKey);
        $data = array_map(
            fn(InvoiceItem $item) => $this->entityToArray($item),
            $items
        );

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        string $invoiceId,
        ?int $productId = null,
        ?string $positionType = null,
        ?string $name = null,
        ?string $description = null,
        ?int $quantity = null,
        ?int $unitPriceCents = null,
        ?int $totalCents = null,
    ): JSONResponse {
        $item = new InvoiceItem();
        $item->setInvoiceId((int)$invoiceId);
        $item->setProductId($productId);
        $item->setPositionType($positionType);
        $item->setName($name);
        $item->setDescription($description);
        $item->setQuantity($quantity);
        $item->setUnitPriceCents($unitPriceCents);
        $item->setTotalCents($totalCents);
        $item->setCreatedAt(time());
        $item->setUpdatedAt(time());

        $saved = $this->invoiceItemMapper->insert($item);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?int $productId = null,
        ?string $positionType = null,
        ?string $name = null,
        ?string $description = null,
        ?int $quantity = null,
        ?int $unitPriceCents = null,
        ?int $totalCents = null,
    ): JSONResponse {
        $itemId = (int)$id;
        try {
            /** @var InvoiceItem $item */
            $item = $this->invoiceItemMapper->find($itemId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Position nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $item->setProductId($productId);
        $item->setPositionType($positionType);
        $item->setName($name);
        $item->setDescription($description);
        $item->setQuantity($quantity);
        $item->setUnitPriceCents($unitPriceCents);
        $item->setTotalCents($totalCents);
        $item->setUpdatedAt(time());

        $saved = $this->invoiceItemMapper->update($item);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $itemId = (int)$id;
        try {
            /** @var InvoiceItem $item */
            $item = $this->invoiceItemMapper->find($itemId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Position nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->invoiceItemMapper->delete($item);
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
