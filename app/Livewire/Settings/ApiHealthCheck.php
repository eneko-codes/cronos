<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use Exception;
use Livewire\Component;

/**
 * Livewire component for testing API connections.
 *
 * Provides ping buttons to test connectivity to each external API.
 */
class ApiHealthCheck extends Component
{
    /**
     * Stores the status of recent connection tests.
     *
     * @var array<string, string>
     */
    public array $connectionStatus = [];

    /**
     * Test connection to a specific platform API.
     */
    private function testConnection(
        string $platform,
        ?OdooApiClient $odooClient = null,
        ?DesktimeApiClient $desktimeClient = null,
        ?ProofhubApiClient $proofhubClient = null,
        ?SystemPinApiClient $systemPinClient = null
    ): void {
        $this->connectionStatus[$platform] = 'pending';

        try {
            $client = match ($platform) {
                'Odoo' => $odooClient ?? app(OdooApiClient::class),
                'DeskTime' => $desktimeClient ?? app(DesktimeApiClient::class),
                'ProofHub' => $proofhubClient ?? app(ProofhubApiClient::class),
                'SystemPin' => $systemPinClient ?? app(SystemPinApiClient::class),
                default => null,
            };

            if ($client) {
                $result = $client->ping();
                $this->connectionStatus[$platform] = $result['success'] ? 'success' : 'failed';
            } else {
                $this->connectionStatus[$platform] = 'failed';
            }
        } catch (Exception $e) {
            $this->connectionStatus[$platform] = 'failed';
        }
    }

    /**
     * Dispatch a toast notification with the result of the ping.
     */
    private function dispatchPingToast(string $platform): void
    {
        $status = $this->connectionStatus[$platform] ?? 'failed';

        if ($status === 'success') {
            $this->dispatch('add-toast', message: "{$platform} connection successful!", variant: 'success');
        } elseif ($status === 'pending') {
            $this->dispatch('add-toast', message: "{$platform} connection is pending...", variant: 'info');
        } else {
            $this->dispatch('add-toast', message: "{$platform} connection failed.", variant: 'error');
        }
    }

    /**
     * Test Odoo API connection.
     */
    public function pingOdoo(OdooApiClient $client): void
    {
        $this->testConnection('Odoo', odooClient: $client);
        $this->dispatchPingToast('Odoo');
    }

    /**
     * Test DeskTime API connection.
     */
    public function pingDesktime(DesktimeApiClient $client): void
    {
        $this->testConnection('DeskTime', desktimeClient: $client);
        $this->dispatchPingToast('DeskTime');
    }

    /**
     * Test ProofHub API connection.
     */
    public function pingProofhub(ProofhubApiClient $client): void
    {
        $this->testConnection('ProofHub', proofhubClient: $client);
        $this->dispatchPingToast('ProofHub');
    }

    /**
     * Test SystemPin API connection.
     */
    public function pingSystemPin(SystemPinApiClient $client): void
    {
        $this->testConnection('SystemPin', systemPinClient: $client);
        $this->dispatchPingToast('SystemPin');
    }

    public function render()
    {
        return view('livewire.settings.api-health-check');
    }
}
