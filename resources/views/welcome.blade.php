<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>DNS Checker</title>
      <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  </head>
  <body class="bg-gradient-to-br from-sky-50 to-indigo-100 min-h-screen">
    <div class="min-h-full">
      <main class="p-4">
        <div class="mx-auto max-w-lg">
          <div class="bg-white p-8 rounded-xl shadow-xl transform transition-all duration-500 ease-in-out">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-8">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 inline-block mr-2 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
              </svg>
              DNS Checker
            </h1>

            <form method="GET" action="/" class="space-y-5">
              <div>
                <label for="domain" class="sr-only">Domain Name</label>
                <input
                  type="text"
                  name="domain"
                  id="domain"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200 ease-in-out text-lg"
                  placeholder="Enter domain (e.g., google.com)"
                  value="{{ request('domain', '') }}"
                  required
                  aria-label="Domain Name Input"
                >
              </div>
              <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-300 ease-in-out transform hover:-translate-y-1 flex items-center justify-center text-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Check DNS
              </button>
            </form>

            @if($records)
              <div class="mt-8 space-y-6">
                @if(count($matchedServers) > 0)
                  <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md">
                    <h3 class="font-semibold text-lg mb-2">Domain is hosted on our servers:</h3>
                    <div class="space-y-3">
                      @foreach($matchedServers as $server)
                        <div class="bg-white p-3 rounded-md shadow-sm">
                          <p class="font-medium">{{ $server['name'] }}</p>
                          <p class="text-sm text-gray-600">IP: {{ $server['ip'] }}</p>
                          <a href="{{ $server['url'] }}" target="_blank" 
                             class="text-indigo-600 hover:text-indigo-800 text-sm inline-flex items-center mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Access Control Panel
                          </a>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @else
                  <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md">
                    <p>This domain is not hosted on any of our known servers.</p>
                  </div>
                @endif

                @if($isCloudflare)
                  <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-md">
                    <div class="flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                      </svg>
                      <p class="font-medium">This domain is using OTM Cloudflare DNS</p>
                    </div>
                  </div>
                @endif

                @php $delay = 0; @endphp
                @foreach($records as $type => $typeRecords)
                  <div class="overflow-hidden rounded-lg bg-white shadow result-item" style="animation-delay: {{ $delay * 0.05 }}s;">
                    <div class="px-4 py-5 sm:p-6">
                      <h3 class="text-lg font-semibold text-gray-700 mb-2">{{ $type }} Records</h3>
                      @if(count($typeRecords) > 0)
                        <div class="space-y-4">
                          @foreach($typeRecords as $record)
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200 shadow-sm">
                              <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                                <div>
                                  <dt class="text-sm font-medium text-gray-500">Host</dt>
                                  <dd class="text-sm text-gray-900">{{ (new ReflectionClass($record))->getProperty('host')->getValue($record) }}</dd>
                                </div>
                                @if(property_exists($record, 'ip'))
                                  <div>
                                    <dt class="text-sm font-medium text-gray-500">IP</dt>
                                    <dd class="text-sm text-gray-900">{{ (new ReflectionClass($record))->getProperty('ip')->getValue($record) }}</dd>
                                  </div>
                                @endif
                                @if(property_exists($record, 'target'))
                                  <div>
                                    <dt class="text-sm font-medium text-gray-500">Target</dt>
                                    <dd class="text-sm text-gray-900">{{ (new ReflectionClass($record))->getProperty('target')->getValue($record) }}</dd>
                                  </div>
                                @endif
                              </dl>
                            </div>
                          @endforeach
                        </div>
                      @else
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md shadow-sm">
                          <p>No records found.</p>
                        </div>
                      @endif
                    </div>
                  </div>
                  @php $delay++; @endphp
                @endforeach
              </div>
            @endif
          </div>
        </div>
      </main>
    </div>

    <style>
      .result-item {
        opacity: 0;
        animation: slideIn 0.5s ease-out forwards;
      }
      
      @keyframes slideIn {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    </style>
  </body>
</html>

