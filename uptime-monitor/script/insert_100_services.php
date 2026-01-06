<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once 'bootstrap/app.php';

// Boot the application
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Now we can use DB facade
use Illuminate\Support\Facades\DB;

echo "🚀 Inserting 100 services into database...\n\n";

$services = [
    'https://www.google.com',
    'https://www.youtube.com',
    'https://www.facebook.com',
    'https://www.wikipedia.org',
    'https://www.twitter.com',
    'https://www.instagram.com',
    'https://www.reddit.com',
    'https://www.tiktok.com',
    'https://www.linkedin.com',
    'https://www.bing.com',
    'https://api.github.com',
    'https://jsonplaceholder.typicode.com/todos/1',
    'https://reqres.in/api/users',
    'https://api.spacexdata.com/v4/launches/latest',
    'https://api.coindesk.com/v1/bpi/currentprice.json',
    'https://api.publicapis.org/entries',
    'https://dog.ceo/api/breeds/image/random',
    'https://api.thecatapi.com/v1/images/search',
    'https://pokeapi.co/api/v2/pokemon/1',
    'https://api.agify.io/?name=michael',
    'https://api.genderize.io/?name=john',
    'https://api.nationalize.io/?name=david',
    'https://api.ipify.org?format=json',
    'https://worldtimeapi.org/api/timezone/Etc/UTC',
    'https://httpbin.org/get',
    'https://httpbin.org/uuid',
    'https://httpbin.org/json',
    'https://httpbin.org/xml',
    'https://httpbin.org/delay/1',
    'https://httpstat.us/200',
    'https://httpstat.us/201',
    'https://httpstat.us/204',
    'https://httpstat.us/301',
    'https://httpstat.us/302',
    'https://www.bbc.com',
    'https://www.cnn.com',
    'https://www.nytimes.com',
    'https://www.kompas.com',
    'https://www.detik.com',
    'https://www.antaranews.com',
    'https://www.cnbc.com',
    'https://www.theguardian.com',
    'https://www.aljazeera.com',
    'https://www.reuters.com',
    'https://www.stackoverflow.com',
    'https://news.ycombinator.com',
    'https://www.techcrunch.com',
    'https://www.theverge.com',
    'https://producthunt.com',
    'https://www.khanacademy.org',
    'https://www.coursera.org',
    'https://www.edx.org',
    'https://www.freecodecamp.org',
    'https://www.usa.gov',
    'https://www.gov.uk',
    'https://indonesia.go.id',
    'https://www.amazon.com',
    'https://www.alibaba.com',
    'https://www.tokopedia.com',
    'https://www.shopee.co.id',
    'https://status.cloud.google.com',
    'https://status.aws.amazon.com',
    'https://status.azure.com',
    'https://status.heroku.com',
    'https://www.cloudflare.com',
    'https://developers.cloudflare.com',
    'https://vercel.com',
    'https://netlify.com',
    'https://api.chucknorris.io/jokes/random',
    'https://official-joke-api.appspot.com/jokes/random',
    'https://api.exchangerate.host/latest',
    'https://catfact.ninja/fact',
    'https://www.metaweather.com/api/location/2487956/',
    'https://bappeda.semarangkota.go.id/rpjmd',
    'https://bappeda.semarangkota.go.id/goessti',
    'https://pantaupemilu.semarangkota.go.id',
    'https://pantausemar.semarangkota.go.id',
    'https://api-ruangwarga.semarangkota.go.id/',
    'https://ruangwarga.semarangkota.go.id',
    'https://siapung.semarangkota.go.id/',
    'https://siamanah.semarangkota.go.id/',
    'https://siap-geo.semarangkota.go.id',
    'https://siap.semarangkota.go.id/',
    'https://simkraf.semarangkota.go.id/',
    'https://kinerja-nonasn.semarangkota.go.id/',
    'https://sikap.semarangkota.go.id/',        
    'https://matalentik.semarangkota.go.id/',
    'https://matasiintel.semarangkota.go.id/',
    'https://mmpi.semarangkota.go.id/',
    'https://sianjar.semarangkota.go.id/',
    'https://asikmas.semarangkota.go.id/',
    'https://perpustakaan.semarangkota.go.id/',
    'https://eling.semarangkota.go.id/',
    'https://jdih.semarangkota.go.id/',
    'https://disdaldukkb.semarangkota.go.id/',
    'https://pitantik.semarangkota.go.id/',
    'https://simpen.semarangkota.go.id/',
    'https://makam.semarangkota.go.id/',
    'https://siedisperkim.semarangkota.go.id/',
    'https://sigpju.semarangkota.go.id/',
    'https://issp.semarangkota.go.id/',
    'https://savira.semarangkota.go.id',
    'https://sijali.semarangkota.go.id/',
    'https://rusun.semarangkota.go.id/',
    'https://semarm-admin.semarangkota.go.id/',
    'https://semarm-api.semarangkota.go.id/',
    'https://semarmrantasi.semarangkota.go.id/',
    'https://sumringah.semarangkota.go.id',
    'https://ppid.semarangkota.go.id/',
    'https://silintas.semarangkota.go.id/',
    'https://lapor.semarangkota.go.id/',
    'https://siap-ppid.semarangkota.go.id/',
    'https://cms.semarangkota.go.id',
    'https://arsipdisperkim.semarangkota.go.id/',
    'https://sibooky.semarangkota.go.id/',
    'https://sangjuara.semarangkota.go.id/',
    'https://ruang-warga.semarangkota.go.id',
    'https://sim-pkk.semarangkota.go.id/',
    'https://apiwa.semarangkota.go.id',
    'https://glpi.semarangkota.go.id'
];

$categories = [
    'Social Media', 'Search Engine', 'News', 'Education', 'Government',
    'E-commerce', 'Cloud Service', 'API', 'Development', 'Entertainment'
];

$intervals = [30, 60, 120, 300, 600]; // 30s, 1m, 2m, 5m, 10m

function generateServiceName($domain, $index) {
    // Remove www. prefix
    $cleanDomain = preg_replace('/^www\./', '', $domain);
    
    // Convert domain to readable name
    $name = str_replace(['.com', '.org', '.net', '.co.id', '.go.id', '.gov', '.edu'], '', $cleanDomain);
    $name = str_replace('.', '-', $name);
    $name = ucwords(str_replace('-', ' ', $name));
    
    // Add service type suffix based on domain patterns
    if (strpos($domain, 'api.') === 0) {
        $name .= ' API';
    } elseif (strpos($cleanDomain, 'status.') === 0) {
        $name .= ' Status Page';
    } elseif (in_array($cleanDomain, ['github.com', 'stackoverflow.com', 'vercel.com', 'netlify.com'])) {
        $name .= ' Platform';
    } elseif (strpos($cleanDomain, 'httpbin') !== false || strpos($cleanDomain, 'httpstat') !== false) {
        $name = "Test Service #$index";
    }
    
    return $name;
}

$created = 0;
$skipped = 0;

foreach ($services as $index => $serviceUrl) {
    $domain = parse_url($serviceUrl, PHP_URL_HOST);
    $name = generateServiceName($domain, $index + 1);
    
    // Check if monitor already exists
    $existing = DB::table('monitors')->where('target', $serviceUrl)->first();
    
    if ($existing) {
        echo "⏭️  Skipped (exists): $name\n";
        $skipped++;
        continue;
    }
    
    try {
        DB::table('monitors')->insert([
            'name' => $name,
            'target' => $serviceUrl,
            'type' => 'https',
            'interval_seconds' => $intervals[array_rand($intervals)],
            'enabled' => 1,
            'group_name' => $categories[array_rand($categories)],
            'created_by' => 1, // Default user ID
            'next_check_at' => date('Y-m-d H:i:s', time() + rand(1, 60)), // Stagger initial checks
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "✅ Created: $name -> $serviceUrl\n";
        $created++;
        
    } catch (Exception $e) {
        echo "❌ Failed to create monitor for $serviceUrl: " . $e->getMessage() . "\n";
    }
}

$totalMonitors = DB::table('monitors')->count();

echo "\n🚀 Test Service Generation Complete!\n";
echo "✅ Created: $created monitors\n";
echo "⏭️  Skipped: $skipped existing monitors\n";
echo "📊 Total services in database: $totalMonitors\n";

if ($created > 0) {
    echo "\n🎯 Ready for durability testing with $created new services!\n";
    echo "💡 Run monitoring commands to start continuous monitoring\n";
}

echo "\nDone! ✨\n";