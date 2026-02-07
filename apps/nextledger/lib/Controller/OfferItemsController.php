<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\OfferItem;
use OCA\NextLedger\Db\OfferItemMapper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class OfferItemsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private OfferItemMapper $offerItemMapper,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(string $offerId): JSONResponse {
        $offerKey = (int)$offerId;
        $items = $this->offerItemMapper->findByOfferId($offerKey);
        $data = array_map(
            fn(OfferItem $item) => $this->entityToArray($item),
            $items
        );

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        string $offerId,
        ?int $productId = null,
        ?string $positionType = null,
        ?string $name = null,
        ?string $description = null,
        ?int $quantity = null,
        ?int $unitPriceCents = null,
        ?int $totalCents = null,
    ): JSONResponse {
        $item = new OfferItem();
        $item->setOfferId((int)$offerId);
        $item->setProductId($productId);
        $item->setPositionType($positionType);
        $item->setName($name);
        $item->setDescription($description);
        $item->setQuantity($quantity);
        $item->setUnitPriceCents($unitPriceCents);
        $item->setTotalCents($totalCents);
        $item->setCreatedAt(time());
        $item->setUpdatedAt(time());

        $saved = $this->offerItemMapper->insert($item);
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
            /** @var OfferItem $item */
            $item = $this->offerItemMapper->find($itemId);
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

        $saved = $this->offerItemMapper->update($item);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $itemId = (int)$id;
        try {
            /** @var OfferItem $item */
            $item = $this->offerItemMapper->find($itemId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Position nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->offerItemMapper->delete($item);
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
