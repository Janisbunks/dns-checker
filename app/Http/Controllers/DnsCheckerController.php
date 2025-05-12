<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Dns\Dns;


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
        '147.135.117.26'
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

  public function index()
  {
    return view('welcome');
  }

  public function check(Request $request)
  {
    $records = null;
    $matchedServers = [];
    $isCloudflare = false;

    if ($request->has('domain')) {
      $domain = $request->input('domain');
      $dns = new Dns();

      $records = [
        'A'     => $dns->getRecords($domain, 'A'),
        'NS'    => $dns->getRecords($domain, 'NS'),
      ];

      // Check A records against known server IPs
      foreach ($records['A'] as $record) {
        $ip = (new \ReflectionClass($record))->getProperty('ip')->getValue($record);
        foreach ($this->knownServers as $server) {
          if (in_array($ip, $server['ips'])) {
            $matchedServers[] = [
              'name' => $server['name'],
              'url' => $server['url'],
              'ip' => $ip
            ];
          }
        }
      }

      // Check NS records for Cloudflare
      if (isset($records['NS'])) {
        foreach ($records['NS'] as $record) {
          $target = (new \ReflectionClass($record))->getProperty('target')->getValue($record);
          if (in_array($target, $this->cloudflareNS)) {
            $isCloudflare = true;
          }
        }
      }
    }

    return view('welcome', [
      'records' => $records,
      'matchedServers' => $matchedServers,
      'isCloudflare' => $isCloudflare
    ]);
  }
}
