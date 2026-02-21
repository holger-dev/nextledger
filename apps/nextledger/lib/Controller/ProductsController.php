<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Product;
use OCA\NextLedger\Db\ProductMapper;
use OCA\NextLedger\Service\ActiveCompanyService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ProductsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private ProductMapper $productMapper,
        private ActiveCompanyService $activeCompanyService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function list(): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $items = $this->productMapper->findAllByCompanyId($companyId);
        $data = array_map(fn(Product $product) => $this->entityToArray($product), $items);

        usort($data, static fn(array $a, array $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return new JSONResponse($data);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function create(
        ?string $name = null,
        ?string $description = null,
        ?int $unitPriceCents = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $product = new Product();
        $product->setCompanyId($companyId);
        $product->setName($name);
        $product->setDescription($description);
        $product->setUnitPriceCents($unitPriceCents);
        $product->setCreatedAt(time());
        $product->setUpdatedAt(time());

        $saved = $this->productMapper->insert($product);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function update(
        string $id,
        ?string $name = null,
        ?string $description = null,
        ?int $unitPriceCents = null,
    ): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $productId = (int)$id;
        try {
            /** @var Product $product */
            $product = $this->productMapper->findByIdAndCompanyId($productId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Produkt nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $product->setCompanyId($companyId);
        $product->setName($name);
        $product->setDescription($description);
        $product->setUnitPriceCents($unitPriceCents);
        $product->setUpdatedAt(time());

        $saved = $this->productMapper->update($product);
        return new JSONResponse($this->entityToArray($saved));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $id): JSONResponse {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $productId = (int)$id;
        try {
            /** @var Product $product */
            $product = $this->productMapper->findByIdAndCompanyId($productId, $companyId);
        } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
            return new JSONResponse(['message' => 'Produkt nicht gefunden.'], Http::STATUS_NOT_FOUND);
        }

        $this->productMapper->delete($product);
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
