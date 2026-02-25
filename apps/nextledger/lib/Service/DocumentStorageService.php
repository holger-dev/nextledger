<?php

declare(strict_types=1);

namespace OCA\NextLedger\Service;

use OCA\NextLedger\Db\DocumentSetting;
use OCA\NextLedger\Db\DocumentSettingMapper;
use OCA\NextLedger\Db\FiscalYear;
use OCA\NextLedger\Db\FiscalYearMapper;
use OCP\Files\File;
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
        string $content,
        string $documentType = 'document',
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
        $documentType = strtolower(trim($documentType));
        $useInvoiceFolderVersioning = !empty($settings['keepPdfVersions']) && $documentType === 'invoice';

        if ($useInvoiceFolderVersioning) {
            $folder = $this->ensureFolder($folder, $baseName);
            $relativeFolder .= '/' . $baseName;
            $latestFileName = sprintf('%s.pdf', $baseName);

            if ($folder->nodeExists($latestFileName)) {
                $currentNode = $folder->get($latestFileName);
                if ($currentNode instanceof File) {
                    $versionName = $this->nextVersionedName($folder, $baseName);
                    $this->writeFileContent($folder, $versionName, $currentNode->getContent());
                }
                $currentNode->delete();
            }

            $this->writeFileContent($folder, $latestFileName, $content);
            return $relativeFolder . '/' . $latestFileName;
        }

        $targetName = empty($settings['keepPdfVersions'])
            ? sprintf('%s.pdf', $baseName)
            : $this->nextVersionedName($folder, $baseName);

        $this->writeFileContent($folder, $targetName, $content);
        return $relativeFolder . '/' . $targetName;
    }

    private function writeFileContent(Folder $folder, string $targetName, string $content): void {
        if ($folder->nodeExists($targetName)) {
            $node = $folder->get($targetName);
            if ($node instanceof Folder) {
                $node->delete();
                $file = $folder->newFile($targetName);
            } else {
                $file = $node;
            }
        } else {
            $file = $folder->newFile($targetName);
        }

        if ($file instanceof File) {
            $stream = $file->fopen('wb');
            if (is_resource($stream)) {
                $offset = 0;
                $length = strlen($content);
                $ok = true;
                while ($offset < $length) {
                    $written = fwrite($stream, substr($content, $offset));
                    if ($written === false || $written === 0) {
                        $ok = false;
                        break;
                    }
                    $offset += $written;
                }
                fclose($stream);
                if ($ok && $offset === $length) {
                    return;
                }
            }
            $file->putContent($content);
            return;
        }

        if (method_exists($file, 'putContent')) {
            $file->putContent($content);
            return;
        }

        if (method_exists($file, 'delete')) {
            $file->delete();
        }
        $folder->newFile($targetName, $content);
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
