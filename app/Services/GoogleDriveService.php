<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    public function __construct(
        private GoogleCalendarService $google,
        private SettingService $settings,
    ) {}

    public function configured(): bool
    {
        return $this->google->apiConfigured();
    }

    public function templatesFolderId(): ?string
    {
        $id = trim((string) ($this->settings->get('google_drive_templates_folder_id') ?? ''));

        return $id !== '' ? $id : null;
    }

    public function contractsFolderId(): ?string
    {
        $id = trim((string) ($this->settings->get('google_drive_contracts_folder_id') ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * @return list<array{id: string, name: string, mimeType: string, webViewLink: ?string}>
     */
    public function listFolder(?string $folderId, int $limit = 40): array
    {
        if (! $this->configured() || ! filled($folderId)) {
            return [];
        }

        try {
            $response = Http::withToken($this->google->freshAccessToken())
                ->get('https://www.googleapis.com/drive/v3/files', [
                    'q' => sprintf("'%s' in parents and trashed = false", str_replace("'", "\\'", $folderId)),
                    'fields' => 'files(id,name,mimeType,webViewLink)',
                    'pageSize' => min(100, max(1, $limit)),
                    'orderBy' => 'name',
                    'supportsAllDrives' => 'true',
                    'includeItemsFromAllDrives' => 'true',
                ])
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('Google Drive listFolder failed', ['error' => $e->getMessage()]);

            return [];
        }

        $files = [];
        foreach ((array) ($response['files'] ?? []) as $file) {
            if (! is_array($file) || blank($file['id'] ?? null)) {
                continue;
            }
            $files[] = [
                'id' => (string) $file['id'],
                'name' => (string) ($file['name'] ?? $file['id']),
                'mimeType' => (string) ($file['mimeType'] ?? ''),
                'webViewLink' => isset($file['webViewLink']) ? (string) $file['webViewLink'] : null,
            ];
        }

        return $files;
    }

    /**
     * @return array{ok: bool, id?: string, webViewLink?: string, message: string}
     */
    public function copyTemplateToContracts(string $fileId, string $newName): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Conta Google não ligada.'];
        }

        $folder = $this->contractsFolderId();
        if (! filled($folder)) {
            return ['ok' => false, 'message' => 'Defina a pasta de contratos gerados nas Configurações.'];
        }

        try {
            $payload = [
                'name' => $newName,
                'parents' => [$folder],
            ];

            $response = Http::withToken($this->google->freshAccessToken())
                ->post(
                    'https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'/copy?supportsAllDrives=true',
                    $payload
                )
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('Google Drive copy failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Falha ao copiar o modelo: '.$e->getMessage()];
        }

        $id = (string) ($response['id'] ?? '');
        $link = 'https://drive.google.com/file/d/'.$id.'/view';

        return [
            'ok' => true,
            'id' => $id,
            'webViewLink' => $link,
            'message' => 'Documento criado no Drive.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testFolders(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Ligue a conta Google (Calendar + Drive).'];
        }

        $templates = $this->templatesFolderId();
        $contracts = $this->contractsFolderId();
        if (! $templates && ! $contracts) {
            return ['ok' => false, 'message' => 'Cole os IDs das pastas de modelos e de contratos gerados.'];
        }

        $n = count($this->listFolder($templates ?: $contracts, 5));

        return [
            'ok' => true,
            'message' => 'Drive OK — '.$n.' ficheiro(s) visíveis na pasta consultada.',
        ];
    }
}
