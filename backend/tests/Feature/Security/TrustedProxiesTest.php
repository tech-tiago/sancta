<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * IP real do visitante atrás da Cloudflare (TRUSTED_PROXIES=cloudflare). O X-Forwarded-For só vale
 * quando a conexão vem de um proxy confiável, e nunca é possível forjar Host ou porta por cabeçalho.
 */
class TrustedProxiesTest extends TestCase
{
    private const CLOUDFLARE = '173.245.48.10';

    private const VISITANTE = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__teste-ip', fn (Request $request) => [
            'ip' => $request->ip(),
            'host' => $request->getHost(),
            'secure' => $request->isSecure(),
        ]);
    }

    private function requestFrom(string $remoteAddr, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])->getJson('/__teste-ip', $headers);
    }

    public function test_without_trusted_proxies_forwarded_headers_are_ignored(): void
    {
        config(['sancta.security.trusted_proxies' => '']);

        $this->requestFrom(self::CLOUDFLARE, ['X-Forwarded-For' => self::VISITANTE])->assertJson(['ip' => self::CLOUDFLARE]);
    }

    public function test_cloudflare_connection_reveals_the_visitor_ip_and_https(): void
    {
        config(['sancta.security.trusted_proxies' => 'cloudflare']);

        $this->requestFrom(self::CLOUDFLARE, ['X-Forwarded-For' => self::VISITANTE, 'X-Forwarded-Proto' => 'https'])
            ->assertJson(['ip' => self::VISITANTE, 'secure' => true]);
    }

    public function test_visitor_cannot_forge_its_ip_through_cloudflare(): void
    {
        config(['sancta.security.trusted_proxies' => 'cloudflare']);

        // A Cloudflare acrescenta o IP real ao X-Forwarded-For que o visitante enviou.
        $this->requestFrom(self::CLOUDFLARE, ['X-Forwarded-For' => '6.6.6.6, '.self::VISITANTE])->assertJson(['ip' => self::VISITANTE]);
    }

    public function test_direct_connection_to_the_origin_cannot_forge_its_ip(): void
    {
        config(['sancta.security.trusted_proxies' => 'cloudflare']);

        $this->requestFrom('198.51.100.20', ['X-Forwarded-For' => self::VISITANTE])->assertJson(['ip' => '198.51.100.20']);
    }

    public function test_forwarded_host_and_port_are_never_trusted(): void
    {
        config(['sancta.security.trusted_proxies' => 'cloudflare']);

        $response = $this->requestFrom(self::CLOUDFLARE, ['X-Forwarded-Host' => 'malicioso.example', 'X-Forwarded-Port' => '8443']);

        $this->assertNotSame('malicioso.example', $response->json('host'));
    }

    public function test_server_maintained_list_replaces_the_bundled_one_only_when_valid(): void
    {
        $file = storage_path('framework/testing/cloudflare-ips.txt');
        @mkdir(dirname($file), 0777, true);
        config(['sancta.security.cloudflare_ips_file' => $file]);

        file_put_contents($file, implode("\n", array_merge(['192.0.2.0/24'], array_fill(0, 10, '10.0.0.0/8'))));
        $this->assertContains('192.0.2.0/24', TrustProxies::resolve('cloudflare'));
        $this->assertNotContains('173.245.48.0/20', TrustProxies::resolve('cloudflare'));

        // Arquivo vazio, curto ou com lixo: volta para a lista do código.
        file_put_contents($file, "192.0.2.0/24\nnão é ip");
        $this->assertContains('173.245.48.0/20', TrustProxies::resolve('cloudflare'));

        @unlink($file);
    }

    public function test_invalid_entries_are_ignored(): void
    {
        $this->assertSame(['10.0.0.1', '2001:db8::/32'], TrustProxies::resolve(' 10.0.0.1, *, 10.0.0.0/99, 2001:db8::/32, banana '));
    }
}
