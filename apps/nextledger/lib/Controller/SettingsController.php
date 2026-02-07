<?php

declare(strict_types=1);

namespace OCA\NextLedger\Controller;

use OCA\NextLedger\Db\Company;
use OCA\NextLedger\Db\CompanyMapper;
use OCA\NextLedger\Db\MiscSetting;
use OCA\NextLedger\Db\MiscSettingMapper;
use OCA\NextLedger\Db\TaxSetting;
use OCA\NextLedger\Db\TaxSettingMapper;
use OCA\NextLedger\Db\Texts;
use OCA\NextLedger\Db\TextsMapper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends ApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private CompanyMapper $companyMapper,
        private TextsMapper $textsMapper,
        private TaxSettingMapper $taxSettingMapper,
        private MiscSettingMapper $miscSettingMapper,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getCompany(): JSONResponse {
        $company = $this->getSingleton($this->companyMapper, Company::class);
        return new JSONResponse($company);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveCompany(
        ?string $name = null,
        ?string $ownerName = null,
        ?string $street = null,
        ?string $houseNumber = null,
        ?string $zip = null,
        ?string $city = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $vatId = null,
        ?string $taxId = null,
    ): JSONResponse {
        /** @var Company $company */
        $company = $this->getSingletonEntity($this->companyMapper, Company::class);
        $company->setName($name);
        $company->setOwnerName($ownerName);
        $company->setStreet($street);
        $company->setHouseNumber($houseNumber);
        $company->setZip($zip);
        $company->setCity($city);
        $company->setEmail($email);
        $company->setPhone($phone);
        $company->setVatId($vatId);
        $company->setTaxId($taxId);
        $saved = $this->persistSingleton($this->companyMapper, $company);

        return new JSONResponse($saved);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTexts(): JSONResponse {
        $texts = $this->getSingleton($this->textsMapper, Texts::class);
        return new JSONResponse($texts);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveTexts(
        ?string $invoiceGreeting = null,
        ?string $offerGreeting = null,
        ?string $footerText = null,
        ?string $offerClosingText = null,
        ?string $invoiceClosingText = null,
        ?string $offerEmailSubject = null,
        ?string $offerEmailBody = null,
        ?string $invoiceEmailSubject = null,
        ?string $invoiceEmailBody = null,
    ): JSONResponse {
        /** @var Texts $texts */
        $texts = $this->getSingletonEntity($this->textsMapper, Texts::class);
        $texts->setInvoiceGreeting($invoiceGreeting);
        $texts->setOfferGreeting($offerGreeting);
        $texts->setFooterText($footerText);
        $texts->setOfferClosingText($offerClosingText);
        $texts->setInvoiceClosingText($invoiceClosingText);
        $texts->setOfferEmailSubject($offerEmailSubject);
        $texts->setOfferEmailBody($offerEmailBody);
        $texts->setInvoiceEmailSubject($invoiceEmailSubject);
        $texts->setInvoiceEmailBody($invoiceEmailBody);
        $saved = $this->persistSingleton($this->textsMapper, $texts);

        return new JSONResponse($saved);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTax(): JSONResponse {
        $tax = $this->getSingleton($this->taxSettingMapper, TaxSetting::class);
        $tax['isSmallBusiness'] = (bool)($tax['isSmallBusiness'] ?? false);
        return new JSONResponse($tax);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveTax(
        ?int $vatRateBp = null,
        ?bool $isSmallBusiness = null,
        ?string $smallBusinessNote = null,
    ): JSONResponse {
        /** @var TaxSetting $tax */
        $tax = $this->getSingletonEntity($this->taxSettingMapper, TaxSetting::class);
        $params = $this->request->getParams();
        if ($vatRateBp === null) {
            $vatRateBp = (int)($params['vatRateBp'] ?? $this->request->getParam('vatRateBp', 0));
        }
        $rawSmallBusiness = $isSmallBusiness;
        if ($rawSmallBusiness === null) {
            $rawSmallBusiness = $params['isSmallBusiness'] ?? $this->request->getParam('isSmallBusiness', null);
        }
        if ($smallBusinessNote === null) {
            $smallBusinessNote = $params['smallBusinessNote'] ?? $this->request->getParam('smallBusinessNote', null);
        }
        if (is_string($rawSmallBusiness)) {
            $rawSmallBusiness = in_array(strtolower($rawSmallBusiness), ['1', 'true', 'yes', 'on'], true);
        }
        $tax->setVatRateBp($vatRateBp);
        $tax->setIsSmallBusiness((bool)$rawSmallBusiness);
        $tax->setSmallBusinessNote($smallBusinessNote);
        $saved = $this->persistSingleton($this->taxSettingMapper, $tax);
        $saved['isSmallBusiness'] = (bool)($saved['isSmallBusiness'] ?? false);

        return new JSONResponse($saved);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getMisc(): JSONResponse {
        $misc = $this->getSingleton($this->miscSettingMapper, MiscSetting::class);
        return new JSONResponse($misc);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveMisc(
        ?int $paymentTermsDays = null,
        ?string $bankName = null,
        ?string $iban = null,
        ?string $bic = null,
        ?string $accountHolder = null,
    ): JSONResponse {
        /** @var MiscSetting $misc */
        $misc = $this->getSingletonEntity($this->miscSettingMapper, MiscSetting::class);
        $misc->setPaymentTermsDays($paymentTermsDays);
        $misc->setBankName($bankName);
        $misc->setIban($iban);
        $misc->setBic($bic);
        $misc->setAccountHolder($accountHolder);
        $saved = $this->persistSingleton($this->miscSettingMapper, $misc);

        return new JSONResponse($saved);
    }

    private function getSingleton(object $mapper, string $entityClass): array {
        $entity = $this->getSingletonEntity($mapper, $entityClass);
        return $this->entityToArray($entity);
    }

    private function getSingletonEntity(object $mapper, string $entityClass): object {
        $items = $mapper->findAll(1, 0);
        if (!empty($items)) {
            return $items[0];
        }

        return new $entityClass();
    }

    private function persistSingleton(object $mapper, object $entity): array {
        if ($entity->getId() === null) {
            $entity = $mapper->insert($entity);
        } else {
            $entity = $mapper->update($entity);
        }

        return $this->entityToArray($entity);
    }

    private function entityToArray(object $entity): array {
        if (method_exists($entity, 'jsonSerialize')) {
            /** @var array $data */
            $data = $entity->jsonSerialize();
            return $data;
        }

        // NC 32 Entity has public properties but no jsonSerialize
        return get_object_vars($entity);
    }
}
