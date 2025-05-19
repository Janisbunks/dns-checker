<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Dns\Dns;
use Illuminate\Support\Facades\Http;


class DnsCheckerController extends Controller
{
  private $knownServers = [
    [
      'name' => 'Apollo (Plesk)',
      'url' => 'https://apollo.psp.otmsrv.lan/login_up.php',
      'ips' => [
        '51.81.94.22',
        '10.88.2.5',
        '51.81.94.29',
        '51.81.94.16',
        '51.81.94.17',
        '51.81.94.18',
        '51.81.94.19',
        '51.81.94.20',
        '51.81.94.21',
        '51.81.94.23',
        '51.81.94.24'
      ]
    ],
    [
      'name' => 'Artemis (Plesk)',
      'url' => 'https://artemis.psp.otmsrv.lan/login_up.php',
      'ips' => [
        '147.135.117.29',
        '147.135.117.22',
        '10.8.2.29',
        '147.135.117.17',
        '147.135.117.18',
        '147.135.117.19',
        '147.135.117.20',
        '147.135.117.21',
        '147.135.117.23',
        '147.135.117.24',
        '147.135.117.25',
        '147.135.117.26',
        '147.135.117.28'
      ]
    ],
    [
      'name' => 'Brightworks (Cpanel)',
      'url' => 'https://web1.securedserver.org:2087/',
      'ips' => ['68.142.191.135']
    ],
    [
      'name' => 'WHM 2087 (Cpanel)',
      'url' => 'https://new.otmdns.com:2087/',
      'ips' => [
        '147.135.90.197',
        '147.135.119.131',
        '51.81.65.170',
        '147.135.90.195',
        '51.81.65.171',
        '51.81.65.172',
        '51.81.65.173',
        '51.81.65.174',
        '51.81.65.175',
        '51.81.65.176',
        '51.81.65.177',
        '51.81.65.178',
        '51.81.65.179',
        '51.81.65.162',
        '51.81.65.180',
        '51.81.65.181',
        '51.81.65.182',
        '51.81.65.183',
        '51.81.65.184',
        '51.81.65.185',
        '51.81.65.186',
        '51.81.65.187',
        '51.81.65.188',
        '51.81.65.189',
        '51.81.65.163',
        '51.81.65.190',
        '51.81.65.164',
        '51.81.65.165',
        '51.81.65.166',
        '51.81.65.167',
        '51.81.65.168',
        '51.81.65.169'
      ]
    ],
    [
      'name' => 'Old Otm Hosting (Cpanel)',
      'url' => 'https://otmdns.com:2087/',
      'ips' => ['52.73.53.164', '34.192.83.212', '172.17.0.1']
    ],
  ];

  // Add this new array for Cloudflare NS records
  private $cloudflareNS = [
    'dale.ns.cloudflare.com',
    'dora.ns.cloudflare.com',
    'anton.ns.cloudflare.com',
    'ollie.ns.cloudflare.com'
  ];
  private $externalCloudflareNS = [
    'candy.ns.cloudflare.com',
    'garret.ns.cloudflare.com'
  ];

  public function index()
  {
    return view('welcome');
  }

  public function check(Request $request)
  {
    $records = null;
    $matchedServers = [];
    $isCloudflare = false;
    $isExternalCloudflare = false;
    $unmaskCloudflare = false;
    $cloudflareRealIps = [];
    $cloudflareRecords = [];

    if ($request->has('domain')) {
      // Clean the domain input (remove http/https/trailing slashes)
      $domain = $this->cleanDomainInput($request->input('domain'));
      $dns = new Dns();

      $records = [
        'A'     => $dns->getRecords($domain, 'A'),
        'NS'    => $dns->getRecords($domain, 'NS'),
      ];

      // First collect all DNS records with their full hostname info
      $allRecords = [];

      // Get standard DNS records first
      foreach ($records['A'] as $record) {
        $ip = (new \ReflectionClass($record))->getProperty('ip')->getValue($record);
        $hostname = (new \ReflectionClass($record))->getProperty('host')->getValue($record);
        $hostLabel = $this->getHostLabel($hostname, $domain);

        $allRecords[] = [
          'ip' => $ip,
          'hostname' => $hostname,
          'label' => $hostLabel,
          'isProxied' => false
        ];
      }

      // Check NS records for Cloudflare
      if (isset($records['NS'])) {
        foreach ($records['NS'] as $record) {
          $target = (new \ReflectionClass($record))->getProperty('target')->getValue($record);
          if (in_array($target, $this->cloudflareNS)) {
            $isCloudflare = true;

            // Get the Cloudflare records with proxy status
            $cfResults = $this->getCloudflareRecords($domain);
            $cloudflareRealIps = $cfResults['realIps'];
            $cloudflareRecords = $cfResults['records'];
            $unmaskCloudflare = !empty($cloudflareRealIps);

            // Add Cloudflare records to our collection
            if ($unmaskCloudflare) {
              foreach ($cloudflareRecords as $cfRecord) {
                $ip = $cfRecord['content'];
                $hostname = $cfRecord['name'];
                $hostLabel = $this->getHostLabel($hostname, $domain);
                $isProxied = $cfRecord['proxied'] === 'Proxied';

                // Check if we already have this record (avoid duplicates)
                $isDuplicate = false;
                foreach ($allRecords as $existingRecord) {
                  if ($existingRecord['ip'] === $ip && $existingRecord['hostname'] === $hostname) {
                    $isDuplicate = true;
                    break;
                  }
                }

                if (!$isDuplicate) {
                  $allRecords[] = [
                    'ip' => $ip,
                    'hostname' => $hostname,
                    'label' => $hostLabel,
                    'isProxied' => $isProxied
                  ];
                }
              }
            }
          }

          // Check if using external Cloudflare
          if (in_array($target, $this->externalCloudflareNS)) {
            $isExternalCloudflare = true;
          }
        }
      }

      // Now match all IP addresses to known servers
      $processedIps = [];

      foreach ($allRecords as $record) {
        $ip = $record['ip'];
        $hostLabel = $record['label'];
        $isProxied = $record['isProxied'];

        // Skip if we already processed this IP
        if (in_array($ip, $processedIps)) {
          continue;
        }

        foreach ($this->knownServers as $server) {
          if (in_array($ip, $server['ips'])) {
            $cfLabel = $isProxied ? ' (Behind Cloudflare)' : '';

            // Include host label directly in server name for visibility
            $serverName = $server['name'];
            if (str_contains($hostLabel, 'Subdomain')) {
              $serverName .= " [{$hostLabel}]";
            }

            $matchedServers[] = [
              'name' => $serverName . $cfLabel,
              'url' => $server['url'],
              'ip' => $ip,
              'host' => $hostLabel
            ];

            $processedIps[] = $ip;
            break; // Stop checking other servers for this IP
          }
        }
      }
    }

    return view('welcome', [
      'records' => $records,
      'matchedServers' => $matchedServers,
      'isCloudflare' => $isCloudflare,
      'isExternalCloudflare' => $isExternalCloudflare,
      'unmaskCloudflare' => $unmaskCloudflare,
      'cloudflareRealIps' => $cloudflareRealIps,
      'cloudflareRecords' => $cloudflareRecords
    ]);
  }

  private function getCloudflareRecords($domain)
  {
    $apiToken = env('CLOUDFLARE_API_TOKEN');
    $result = [
      'realIps' => [],
      'records' => []
    ];

    if (!$apiToken) {
      return $result;
    }

    // Get the zone ID for the domain
    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . $apiToken,
      'Content-Type' => 'application/json',
    ])->get('https://api.cloudflare.com/client/v4/zones', [
      'name' => $domain
    ]);

    if (!$response->successful() || empty($response->json('result'))) {
      return $result;
    }

    $zoneId = $response->json('result.0.id');

    // Get the DNS records for this zone
    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . $apiToken,
      'Content-Type' => 'application/json',
    ])->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", [
      'type' => 'A'
    ]);

    if ($response->successful() && !empty($response->json('result'))) {
      foreach ($response->json('result') as $record) {
        if ($record['type'] === 'A') {
          // Add to real IPs list
          $result['realIps'][] = $record['content'];

          // Add full record info including proxy status
          $result['records'][] = [
            'name' => $record['name'],
            'type' => $record['type'],
            'content' => $record['content'],
            'proxied' => $record['proxied'] ? 'Proxied' : 'DNS Only',
            'ttl' => $record['ttl']
          ];
        }
      }
    }

    return $result;
  }

  /**
   * Clean domain input to remove http/https and trailing slashes
   */
  private function cleanDomainInput($input)
  {
    // Remove http/https protocol
    $domain = preg_replace('#^https?://#', '', $input);
    // Remove trailing slash
    $domain = rtrim($domain, '/');
    // Remove path part after domain if any
    $domain = strtok($domain, '/');
    // Remove www. prefix for consistent domain handling
    $domain = preg_replace('/^www\./', '', $domain);

    return $domain;
  }

  /**
   * Get a human-readable host label that indicates main domain or subdomain
   */
  private function getHostLabel($hostname, $maindomain)
  {
    // Remove trailing dot if present
    $hostname = rtrim($hostname, '.');
    $maindomain = rtrim($maindomain, '.');

    // Strip www. from both hostname and maindomain for comparison
    $hostname = preg_replace('/^www\./', '', $hostname);
    $maindomain = preg_replace('/^www\./', '', $maindomain);

    // If it's the exact domain, it's the main domain
    if ($hostname === $maindomain) {
      return 'Main domain';
    }

    // If it's the www version, it's also the main domain
    if ($hostname === 'www.' . $maindomain) {
      return 'Main domain (www)';
    }

    // If it ends with the main domain, it's a subdomain
    if (preg_match('/(.+)\.' . preg_quote($maindomain, '/') . '$/', $hostname, $matches)) {
      return 'Subdomain: ' . $matches[1];
    }

    // If it's something else, just return the hostname
    return $hostname;
  }
}
