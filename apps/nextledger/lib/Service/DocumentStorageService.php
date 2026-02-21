<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use OCA\NextLedger\Db\DocumentSetting;
use OCA\NextLedger\Db\DocumentSettingMapper;
use OCA\NextLedger\Db\FiscalYear;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

class DocumentStorageService {
    public function __construct(
        private ActiveCompanyService $activeCompanyService,
        private DocumentSettingMapper $documentSettingMapper,
        private FiscalYearMapper $fiscalYearMapper,
        private IRootFolder $rootFolder,
        private IUserSession $userSession,
    ) {}

    public function storeGeneratedPdf(
        string $documentNumber,
        ?int $documentDateTs,
        string $content
    ): ?string {
        $settings = $this->getSettings();
        if (empty($settings['autoStorePdfs'])) {
            return null;
        }

        $user = $this->userSession->getUser();
        if (!$user || !method_exists($user, 'getUID')) {
            return null;
        }

        $company = $this->activeCompanyService->getActiveCompany();
        $companyId = (int)$company->getId();
        $fiscalYear = $this->resolveFiscalYear($companyId, $documentDateTs);
        $fiscalYearLabel = $fiscalYear?->getName() ?: 'Ohne Wirtschaftsjahr';
        $companyLabel = $company->getName() ?: ('Firma-' . $companyId);

        $relativeFolder = implode('/', [
            'NextLedger',
            $this->sanitizePathPart($companyLabel),
            $this->sanitizePathPart($fiscalYearLabel),
        ]);
        $folder = $this->ensureFolder($this->rootFolder->getUserFolder($user->getUID()), $relativeFolder);

        $baseName = $this->sanitizeFileName($documentNumber);
        $targetName = empty($settings['keepPdfVersions'])
            ? sprintf('%s.pdf', $baseName)
            : $this->nextVersionedName($folder, $baseName);

        if ($folder->nodeExists($targetName)) {
            $file = $folder->get($targetName);
            if (method_exists($file, 'putContent')) {
                $file->putContent($content);
            } else {
                $file->delete();
                $folder->newFile($targetName, $content);
            }
        } else {
            $folder->newFile($targetName, $content);
        }

        return $relativeFolder . '/' . $targetName;
    }

    private function resolveFiscalYear(int $companyId, ?int $documentDateTs): ?FiscalYear {
        if ($documentDateTs) {
            $year = $this->fiscalYearMapper->findByDate($documentDateTs, $companyId);
            if ($year) {
                return $year;
            }
        }

        return $this->fiscalYearMapper->findActive($companyId);
    }

    private function ensureFolder(Folder $baseFolder, string $path): Folder {
        $parts = array_values(array_filter(explode('/', $path)));
        $folder = $baseFolder;
        foreach ($parts as $part) {
            if ($folder->nodeExists($part)) {
                $node = $folder->get($part);
                if ($node instanceof Folder) {
                    $folder = $node;
                    continue;
                }
                $node->delete();
            }
            $folder = $folder->newFolder($part);
        }

        return $folder;
    }

    private function nextVersionedName(Folder $folder, string $baseName): string {
        $prefix = $baseName . '_v';
        $maxVersion = 0;
        foreach ($folder->getDirectoryListing() as $node) {
            $name = $node->getName();
            if (!str_starts_with($name, $prefix) || !str_ends_with($name, '.pdf')) {
                continue;
            }
            if (preg_match('/_v(\d+)\.pdf$/', $name, $matches) === 1) {
                $maxVersion = max($maxVersion, (int)$matches[1]);
            }
        }

        return sprintf('%s_v%d.pdf', $baseName, $maxVersion + 1);
    }

    private function getSettings(): array {
        $companyId = $this->activeCompanyService->getActiveCompanyId();
        $items = $this->documentSettingMapper->findAllByCompanyId($companyId, 1, 0);
        if (empty($items)) {
            return [
                'autoStorePdfs' => false,
                'keepPdfVersions' => false,
            ];
        }

        /** @var DocumentSetting $entity */
        $entity = $items[0];
        return [
            'autoStorePdfs' => (bool)$entity->getAutoStorePdfs(),
            'keepPdfVersions' => (bool)$entity->getKeepPdfVersions(),
        ];
    }

    private function sanitizePathPart(string $value): string {
        $clean = preg_replace('/[\/\\\\]+/', '-', trim($value)) ?: 'Unbenannt';
        return trim($clean);
    }

    private function sanitizeFileName(string $value): string {
        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '_', trim($value)) ?: 'dokument';
        return trim($clean, '._-') ?: 'dokument';
    }
}
