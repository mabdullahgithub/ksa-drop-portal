<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds a fulfilment store with a full order history that lives apart from
 * the platform (clients.is_isolated): none of it reaches an admin list, a
 * stat, a report, a courier sync or a webhook. Only its own sign-in, and the
 * staff sign-in made with --staff-email, can see it. See App\Support\ClientAccess.
 *
 * Writes straight to the tables, so no observer sends a welcome mail or an
 * admin notification, and no stock moves. Tracking numbers sit in ranges the
 * couriers have already used up, so a real booking can never collide with one.
 */
class CreateIsolatedStore extends Command
{
    protected $signature = 'store:isolated
        {name=Rawaj Store : The store name}
        {--email= : Sign-in email for the store owner (default: <store>@ksadrop.com)}
        {--staff-email= : Also create a staff sign-in (admin role) that sees only this store}
        {--orders=2148 : Total orders}
        {--delivered=1453 : Delivered orders}
        {--returned=159 : Returned orders}
        {--purge : Remove the isolated store with this name and everything in it}';

    protected $description = 'Create (or --purge) an isolated fulfilment store with a full order history';

    private const CANCELLED = 120;
    private const UNASSIGNED = 152;
    private const HISTORY_DAYS = 166;

    /** Catalogue titles to stock, with this store's selling price. */
    private const PRODUCTS = [
        ['E99 Drone With 4k Camera', 199, 9],
        ['Jump Starter With Air Pump', 189, 10],
        ['Muscle Massage Gun with 4 Heads', 189, 16],
        ['X8 Robot Vacuum Cleaner', 179, 11],
        ["Men's Stainless Steel Set", 189, 15],
        ['Mini Jet Fan', 159, 12],
        ['Multi-Functional Wireless Charger', 149, 13],
        ['Casio Digital Men Watch', 169, 14],
    ];

    /** city => [weight, province, zip base, [districts], [iMile stations], iMile hub or null] */
    private const CITIES = [
        'Riyadh' => [34, 'Riyadh', 11, ['Al Olaya', 'Al Malaz', 'Al Nakheel', 'Al Yasmin', 'Al Narjis', 'Al Rawdah', 'Al Sulay', 'Al Aziziyah', 'Al Suwaidi', 'Qurtubah', 'Al Muruj', 'Hittin', 'Al Naseem', 'Dhahrat Laban', 'Al Rabwah', 'Al Malqa', 'Ishbiliyah'], ['Riyadh Station', 'Riyadh As Sulay Station', 'Riyadh Olaya Station', 'RUKDS002A', 'Riyadh Naseem Station'], null],
        'Jeddah' => [17, 'Makkah', 21, ['Al Rawdah', 'Al Safa', 'Al Naeem', 'Al Hamra', 'Al Salamah', 'Al Faisaliyah', 'Al Marwah', 'Obhur Al Shamaliyah', 'Al Zahra', 'Al Basateen', 'Al Rehab'], ['JED DS10', 'JED DS11', 'JED DS12', 'Jeddah Khumrah Station', 'JED Nakheel Station'], 'Jeddah'],
        'Makkah' => [7, 'Makkah', 24, ['Al Aziziyah', 'Al Awali', 'Al Shawqiyah', 'Al Naseem', 'Al Zahir', 'Batha Quraish'], ['Macca Station', 'Mecca Al Hujj Station'], 'Jeddah'],
        'Madinah' => [6, 'Madinah', 42, ['Quba', 'Al Aziziyah', 'Al Iskan', 'Al Khalidiyah', 'Shuran', 'Al Aqiq'], ['Madinah Uhud Station', 'Madina Station'], 'Jeddah'],
        'Dammam' => [7, 'Eastern Province', 32, ['Al Faisaliyah', 'Al Shati', 'Al Nur', 'Al Mazruiyah', 'Al Badiyah', 'Uhud', 'Al Jalawiyah'], ['Dammam Station'], 'Dammam'],
        'Al Khobar' => [5, 'Eastern Province', 34, ['Al Aqrabiyah', 'Al Rakah', 'Al Thuqbah', 'Al Ulaya', 'Al Khobar Al Shamaliyah'], ['Al Khobar Station'], 'Dammam'],
        'Al Ahsa' => [3, 'Eastern Province', 36, ['Al Mubarraz', 'Al Hofuf', 'Al Khaldiyah', 'Al Muhammadiyah'], ['Hofuf Station'], 'Dammam'],
        'Taif' => [4, 'Makkah', 26, ['Al Shifa', 'Al Hawiyah', 'Al Faisaliyah', 'Shahar', 'Al Qamariyah'], ['Taif Station'], 'Jeddah'],
        'Tabuk' => [3, 'Tabuk', 47, ['Al Muruj', 'Al Rawdah', 'Al Faisaliyah', 'Al Masif'], ['Tabuk Station'], null],
        'Abha' => [3, 'Asir', 62, ['Al Mansak', 'Al Khalidiyah', 'Al Mahalah', 'Al Wardatain'], ['ABHDS003A'], 'Khamis Mushait'],
        'Khamis Mushait' => [3, 'Asir', 61, ['Al Dhabab', 'Al Mousa', 'Al Rasras', 'Al Ruqi'], ['Khamis Mushait Station'], 'Khamis Mushait'],
        'Buraydah' => [3, 'Qassim', 52, ['Al Iskan', 'Al Safra', 'Al Rayyan', 'Al Nahdah'], ['Buraidah Station'], null],
        'Hail' => [2, 'Hail', 55, ['Al Nugrah', 'Al Aziziyah', 'Al Muntazah', 'Lubdah'], ['Hail Station'], null],
        'Jazan' => [2, 'Jazan', 45, ['Al Suwais', 'Al Rawdah', 'Al Shati', 'Al Matar'], ['Jazan Station'], 'Khamis Mushait'],
        'Najran' => [1, 'Najran', 66, ['Al Faisaliyah', 'Al Fahd', 'Al Aris'], ['Najran Station', 'NJRDS001A'], 'Khamis Mushait'],
        'Hafar Al Batin' => [1, 'Eastern Province', 39, ['Al Rabwah', 'Al Faisaliyah', 'Al Khalidiyah'], ['Hafar Al Batin Station'], 'Dammam'],
        'Al Bahah' => [1, 'Al Bahah', 65, ['Al Aqiq', 'Al Hajrah', 'Al Zahir'], ['Al Bahah Station'], 'Jeddah'],
    ];

    private const STREETS = [
        'King Fahd Rd', 'King Abdulaziz Rd', 'Prince Sultan St', 'Prince Turki St', 'Olaya St', 'Tahlia St',
        'Al Imam Saud St', 'Prince Muhammad bin Abdulaziz St', 'Omar Al Mukhtar St', 'Abu Bakr Al Siddiq Rd',
        'King Khalid St', 'Al Madinah Rd', 'Prince Majed St', 'Hira St', 'Al Ameer Nayef St', 'Uthman bin Affan Rd',
        'Al Takhassusi St', 'Al Urubah Rd', 'Prince Thamer St', 'King Faisal Rd', 'Al Khalij Rd', 'Salman Al Farsi St',
        'Anas bin Malik Rd', 'Al Hamra St', 'Ibn Khaldun St', 'Al Sitteen St', 'Al Batha St', 'Makkah Al Mukarramah Rd',
    ];

    private const MALE = ['Mohammed', 'Abdullah', 'Fahad', 'Khalid', 'Saud', 'Faisal', 'Sultan', 'Turki', 'Nasser', 'Omar', 'Ahmed', 'Ali', 'Yousef', 'Ibrahim', 'Saleh', 'Majed', 'Bandar', 'Nawaf', 'Rakan', 'Hamad', 'Abdulrahman', 'Abdulaziz', 'Mansour', 'Badr', 'Waleed', 'Ziyad', 'Meshal', 'Hussain', 'Hassan', 'Tariq', 'Mishari', 'Osama', 'Yazeed', 'Saad', 'Hatem'];
    private const FEMALE = ['Noura', 'Sara', 'Reem', 'Lama', 'Hessa', 'Maha', 'Amal', 'Asma', 'Nouf', 'Rana', 'Haya', 'Joud', 'Shahad', 'Ghada', 'Mona', 'Wafa', 'Latifa', 'Dalal', 'Arwa', 'Hind', 'Fatimah', 'Maryam', 'Rawan', 'Lujain', 'Abeer', 'Razan', 'Alanoud'];
    private const FAMILY = ['Al-Harbi', 'Al-Otaibi', 'Al-Qahtani', 'Al-Ghamdi', 'Al-Zahrani', 'Al-Shehri', 'Al-Mutairi', 'Al-Dosari', 'Al-Anazi', 'Al-Shammari', 'Al-Subaie', 'Al-Malki', 'Al-Juhani', 'Al-Rashidi', 'Al-Amri', 'Al-Asmari', 'Al-Sharif', 'Al-Yami', 'Al-Hajri', 'Al-Qarni', 'Al-Balawi', 'Al-Omari', 'Al-Khaldi', 'Al-Saadi', 'Al-Tamimi', 'Al-Faifi', 'Al-Ruwaili', 'Al-Sulami', 'Al-Johani', 'Al-Shahrani'];
    private const AR_FIRST = ['محمد', 'عبدالله', 'فهد', 'خالد', 'سعود', 'فيصل', 'سلطان', 'تركي', 'ناصر', 'عمر', 'أحمد', 'علي', 'يوسف', 'نورة', 'سارة', 'ريم', 'لمى', 'هيا', 'منى', 'أمل', 'نوف', 'عبدالرحمن', 'ماجد', 'بندر'];
    private const AR_FAMILY = ['الحربي', 'العتيبي', 'القحطاني', 'الغامدي', 'الزهراني', 'الشهري', 'المطيري', 'الدوسري', 'العنزي', 'الشمري', 'السبيعي', 'المالكي', 'الجهني', 'الرشيدي', 'العمري', 'اليامي', 'الشهراني'];
    private const EXPAT = ['Muhammad Imran', 'Arshad Khan', 'Rizwan Ahmed', 'Navas Muhammed', 'Joseph Thomas', 'Anwar Hussain', 'Ramesh Kumar', 'Mohamed Salah', 'Ahmed Mostafa', 'Bilal Hussain', 'Shahid Iqbal', 'Abdul Rahman', 'Faizal Kunhi', 'Sajid Ali', 'Mahmoud Adel', 'Usman Ghani', 'Shafeeq Rahman', 'Omar Farouk', 'Tamer Hassan', 'Kareem Nabil', 'Adnan Siddiqui', 'Waqas Ahmed', 'Jibin George', 'Haris Mehmood', 'Mostafa Elsayed'];

    private const FAIL_REASONS = ['No answer.', 'Customer not available.', 'Customer asked to reschedule.', 'Phone switched off.', 'Customer refused the parcel.', 'Incomplete address.'];

    private string $now;

    private string $skuPrefix;

    public function handle(): int
    {
        if (! Schema::hasColumn('clients', 'is_isolated')) {
            $this->error('Run the migrations first (clients.is_isolated is missing).');

            return self::FAILURE;
        }

        $name = trim((string) $this->argument('name'));

        if ($this->option('purge')) {
            return $this->purge($name);
        }

        if (DB::table('clients')->where('company_name', $name)->exists()) {
            $this->error("A client named \"{$name}\" already exists. Use --purge to remove an isolated one first.");

            return self::FAILURE;
        }

        $total = (int) $this->option('orders');
        $delivered = (int) $this->option('delivered');
        $returned = (int) $this->option('returned');
        $active = $total - $delivered - $returned - self::CANCELLED - self::UNASSIGNED;

        if ($active < 0) {
            $this->error('--orders is too small for the delivered/returned counts.');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: Str::slug($name, '.').'@ksadrop.com';
        $staffEmail = $this->option('staff-email');

        foreach (array_filter([$email, $staffEmail]) as $address) {
            if (DB::table('users')->where('email', $address)->exists()) {
                $this->error("A user with {$address} already exists.");

                return self::FAILURE;
            }
        }

        mt_srand(crc32($name));
        $this->now = Carbon::now('UTC')->toDateTimeString();
        $this->skuPrefix = str_pad(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)), 3, 'X');
        $nowTs = Carbon::now('UTC')->getTimestamp();
        $start = Carbon::now('UTC')->subDays(self::HISTORY_DAYS)->startOfDay();
        $admin = (int) (DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'superadmin')->min('model_id') ?? 1);

        $catalogue = $this->pickCatalogue();
        if (count($catalogue) < 4) {
            $this->error('Not enough catalogue products with images to stock the store.');

            return self::FAILURE;
        }

        $password = Str::password(14, symbols: false);
        $staffPassword = $staffEmail ? Str::password(14, symbols: false) : null;

        $this->info('Generating orders...');
        [$orders, $payouts] = $this->generate($catalogue, $start, $nowTs, $total, $delivered, $returned, $active);

        $clientId = DB::transaction(function () use ($name, $email, $password, $staffEmail, $staffPassword, $start, $admin, $catalogue, $orders, $payouts) {
            $since = $start->copy()->subDays(9);
            $clientRole = DB::table('roles')->where('name', 'client')->value('id');

            $userId = DB::table('users')->insertGetId([
                'name' => 'Faisal Al-Harbi',
                'email' => $email,
                'email_verified_at' => $since,
                'password' => Hash::make($password),
                'client_access' => 'all',
                'two_factor_enabled' => false,
                'created_at' => $since,
                'updated_at' => $since,
            ]);
            DB::table('model_has_roles')->insert(['role_id' => $clientRole, 'model_type' => \App\Models\User::class, 'model_id' => $userId]);

            $shortId = Client::generateClientId($name);
            $clientId = DB::table('clients')->insertGetId([
                'user_id' => $userId,
                'created_by' => $admin,
                'client_types' => json_encode(['fulfilment']),
                'company_name' => $name,
                'short_id' => $shortId,
                'contact_person' => 'Faisal Al-Harbi',
                'phone' => '+966553718402',
                'address' => '7421 Prince Turki St, Al Nakheel',
                'city' => 'Riyadh',
                'country' => 'SA',
                'postal_code' => '12385',
                'commercial_registration' => '1010846215',
                'status' => 'active',
                'is_isolated' => true,
                'portal_features' => json_encode(['orders', 'revenue', 'finance', 'inventory']),
                'charges' => json_encode(['delivery' => 25, 'return' => 10, 'call_confirmation' => 1.5]),
                'created_at' => $since,
                'updated_at' => $since,
            ]);

            if ($staffEmail) {
                $staffId = DB::table('users')->insertGetId([
                    'name' => 'Operations',
                    'email' => $staffEmail,
                    'email_verified_at' => $since,
                    'password' => Hash::make($staffPassword),
                    'client_access' => 'assigned',
                    'two_factor_enabled' => false,
                    'created_at' => $since,
                    'updated_at' => $since,
                ]);
                DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', 'admin')->value('id'), 'model_type' => \App\Models\User::class, 'model_id' => $staffId]);
                DB::table('client_user_access')->insert(['user_id' => $staffId, 'client_id' => $clientId, 'created_at' => $since, 'updated_at' => $since]);
            }

            // Products, in stock: only warehouse scans move stock, and there are none.
            $productIds = [];
            foreach ($catalogue as $i => $product) {
                $added = $start->copy()->subDays(7)->addHours($i * 3);
                $productIds[$i] = DB::table('client_products')->insertGetId([
                    'client_id' => $clientId,
                    'product_code' => $this->productCode($shortId, $i + 1),
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'quantity' => [148, 96, 212, 74, 165, 230, 318, 187][$i % 8],
                    'unit_price' => $product['price'],
                    'verification_status' => 'verified',
                    'verified_at' => $added->copy()->addHours(20),
                    'verified_by' => $admin,
                    'is_out_of_stock' => false,
                    'created_at' => $added,
                    'updated_at' => $added->copy()->addHours(20),
                ]);
            }

            $number = 0;
            foreach (array_chunk($orders, 250) as $chunk) {
                $rows = [];
                foreach ($chunk as $o) {
                    $rows[] = array_merge($o['order'], [
                        'client_id' => $clientId,
                        'order_number' => $shortId.str_pad((string) ++$number, 5, '0', STR_PAD_LEFT),
                    ]);
                }
                DB::table('orders')->insert($rows);

                $ids = DB::table('orders')->whereIn('order_number', array_column($rows, 'order_number'))->pluck('id', 'order_number');

                $items = [];
                $shipments = [];
                foreach ($chunk as $k => $o) {
                    $orderNumber = $rows[$k]['order_number'];
                    $orderId = $ids[$orderNumber];

                    foreach ($o['items'] as $item) {
                        $items[] = array_merge($item, ['order_id' => $orderId, 'client_product_id' => $productIds[$item['client_product_id']]]);
                    }
                    if ($o['shipment']) {
                        $shipments[] = array_merge($o['shipment'], ['order_id' => $orderId, 'txlogistic_id' => $orderNumber]);
                    }
                }
                DB::table('order_items')->insert($items);
                if ($shipments) {
                    DB::table('shipments')->insert($shipments);
                }
            }

            foreach ($payouts as $payout) {
                DB::table('client_payments')->insert(array_merge($payout, ['client_id' => $clientId, 'created_by' => $admin]));
            }

            return $clientId;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->attachImages($clientId, $catalogue);

        $this->newLine();
        $this->info("Created \"{$name}\" (client #{$clientId}), isolated from the platform.");
        $this->table(['', 'Value'], [
            ['Orders', $total],
            ['Delivered', $delivered],
            ['Returned', $returned],
            ['In delivery', $active],
            ['Cancelled', self::CANCELLED],
            ['Awaiting dispatch', self::UNASSIGNED],
            ['Store sign-in', "{$email} / {$password}"],
            ['Staff sign-in', $staffEmail ? "{$staffEmail} / {$staffPassword}" : '—'],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, sku: string, price: int, weight: int, image: ?string}>
     */
    private function pickCatalogue(): array
    {
        $picked = [];
        $used = [];

        foreach (self::PRODUCTS as [$title, $price, $weight]) {
            $product = DB::table('products')->where('title', 'like', "%{$title}%")->whereNotIn('id', $used ?: [0])
                ->whereNotNull('primary_image')->orderBy('id')->first(['id', 'title', 'primary_image']);

            // Missing from this catalogue: any other product with an image.
            $product ??= DB::table('products')->whereNotNull('primary_image')->where('primary_image', '!=', '')
                ->whereNotIn('id', $used ?: [0])->inRandomOrder(crc32($title))->first(['id', 'title', 'primary_image']);

            if (! $product) {
                continue;
            }

            $used[] = $product->id;
            $picked[] = [
                'name' => trim(preg_replace('/\s+/', ' ', trim($product->title, " \"'")), ' ,'),
                'sku' => $this->skuPrefix.'-'.(1100 + count($picked) * 7 + 3),
                'price' => $price,
                'weight' => $weight,
                'image' => $product->primary_image,
            ];
        }

        return $picked;
    }

    private function productCode(string $shortId, int $n): string
    {
        $n--;
        do {
            $code = $shortId.'-'.str_pad((string) ++$n, 3, '0', STR_PAD_LEFT);
        } while (DB::table('client_products')->where('product_code', $code)->exists());

        return $code;
    }

    /**
     * Every order with its items and shipment, oldest first, and the payouts.
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private function generate(array $catalogue, Carbon $start, int $nowTs, int $total, int $delivered, int $returned, int $active): array
    {
        // When orders came in: growing over the months, busier at the weekend
        // (Thu-Sat) and in the evening, Riyadh time.
        $dayWeights = [];
        for ($d = 0; $d <= self::HISTORY_DAYS; $d++) {
            $date = $start->copy()->addDays($d);
            $w = 4 + 15 * (($d / self::HISTORY_DAYS) ** 1.25);
            if (in_array($date->dayOfWeek, [Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $w *= 1.18;
            }
            $dayWeights[$d] = $w * (0.8 + mt_rand(0, 40) / 100);
        }
        $hourWeights = [1 => 2, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 5, 11 => 5, 12 => 5, 13 => 6, 14 => 6, 15 => 6, 16 => 7, 17 => 8, 18 => 9, 19 => 10, 20 => 11, 21 => 11, 22 => 10, 23 => 7, 0 => 4];

        $times = [];
        while (count($times) < $total) {
            $day = $this->weighted($dayWeights);
            $hour = $this->weighted($hourWeights);
            // Riyadh is UTC+3.
            $ts = $start->getTimestamp() + $day * 86400 + ($hour - 3) * 3600 + mt_rand(0, 3599);
            if ($ts < $nowTs - 900 && $ts > $start->getTimestamp()) {
                $times[] = $ts;
            }
        }
        sort($times);

        // Outcomes. Cancellations fall anywhere; the newest orders are still
        // with the call team or on the road; everything older has finished.
        $outcome = array_fill(0, $total, null);
        $indices = range(0, $total - 1);
        shuffle($indices);
        foreach (array_slice($indices, 0, self::CANCELLED) as $i) {
            $outcome[$i] = 'cancelled';
        }

        $open = [];
        for ($i = $total - 1; $i >= 0 && count($open) < self::UNASSIGNED + $active; $i--) {
            if ($outcome[$i] === null) {
                $open[] = $i;
            }
        }
        // Newer leans unassigned, older leans on the road, with overlap.
        $scored = [];
        foreach ($open as $rank => $i) {
            $scored[$i] = $rank + mt_rand(0, 160);
        }
        asort($scored);
        $scored = array_keys($scored);
        foreach (array_slice($scored, 0, self::UNASSIGNED) as $i) {
            $outcome[$i] = 'unassigned';
        }
        $onRoad = array_slice($scored, self::UNASSIGNED);
        rsort($onRoad);
        $mix = ['in_transit' => 0.42, 'out_for_delivery' => 0.22, 'attempt_fail' => 0.20, 'exception' => 0.16];
        foreach ($onRoad as $n => $i) {
            // The very newest are only just booked.
            if ($n < (int) round($active * 0.18)) {
                $outcome[$i] = 'info_received';
                continue;
            }
            $outcome[$i] = $this->weighted($mix);
        }

        $done = [];
        foreach ($outcome as $i => $o) {
            if ($o === null) {
                $done[] = $i;
            }
        }
        shuffle($done);
        foreach ($done as $n => $i) {
            $outcome[$i] = $n < $returned ? 'returned' : 'delivered';
        }

        $productWeights = array_column($catalogue, 'weight');
        $customers = [];
        $orders = [];
        $settled = [];

        foreach ($times as $i => $ts) {
            $created = Carbon::createFromTimestamp($ts, 'UTC');
            $status = $outcome[$i];

            // Some customers come back.
            $customer = (count($customers) > 40 && mt_rand(1, 100) <= 9)
                ? $customers[array_rand($customers)]
                : $customers[] = $this->customer();

            // Items: mostly one product, sometimes two of it, now and then two products.
            $first = $this->weighted($productWeights);
            $lines = [[$first, mt_rand(1, 100) <= 5 ? 2 : 1]];
            if (mt_rand(1, 100) <= 3) {
                $second = $this->weighted($productWeights);
                if ($second !== $first) {
                    $lines[] = [$second, 1];
                }
            }
            $subtotal = 0;
            $items = [];
            foreach ($lines as [$p, $qty]) {
                $subtotal += $catalogue[$p]['price'] * $qty;
                $items[] = [
                    'lineitem_name' => $catalogue[$p]['name'],
                    'lineitem_quantity' => $qty,
                    'lineitem_price' => $catalogue[$p]['price'],
                    'lineitem_sku' => $catalogue[$p]['sku'],
                    'lineitem_requires_shipping' => true,
                    'lineitem_taxable' => false,
                    'lineitem_fulfillment_status' => in_array($status, ['delivered'], true) ? 'fulfilled' : 'unfulfilled',
                    'lineitem_discount' => 0,
                    'client_product_id' => $p,
                    'product_id' => null,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }
            // Buying two earns a small discount.
            $discount = (count($lines) > 1 || $lines[0][1] > 1) ? round($subtotal * 0.1) : 0;
            $totalPrice = $subtotal - $discount;

            $calledAt = Carbon::createFromTimestamp(min($created->getTimestamp() + mt_rand(15, 360) * 60, max($ts + 60, $nowTs - 120)), 'UTC');
            $order = [
                'customer_name' => $customer['name'],
                'customer_email' => null,
                'customer_phone' => $customer['phone'],
                'shipping_name' => $customer['name'],
                'shipping_address1' => $customer['address'],
                'shipping_city' => $customer['city'],
                'shipping_zip' => $customer['zip'],
                'shipping_province' => $customer['province'],
                'shipping_country' => 'SA',
                'shipping_phone' => $customer['phone'],
                'financial_status' => 'pending',
                'fulfillment_status' => 'unfulfilled',
                'call_status' => 'confirmed',
                'call_attempts' => mt_rand(1, 100) <= 78 ? 1 : 2,
                'last_called_at' => $calledAt,
                'payment_method' => 'Cash on Delivery',
                'currency' => 'SAR',
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'taxes' => 0,
                'total' => $totalPrice,
                'discount_amount' => $discount,
                'outstanding_balance' => 0,
                'refunded_amount' => 0,
                'tags' => json_encode(['Confirm']),
                'accepts_marketing' => mt_rand(1, 100) <= 35,
                'created_at' => $created,
                'updated_at' => $calledAt,
                'paid_at' => null,
                'fulfilled_at' => null,
                'cancelled_at' => null,
                'call_notes' => null,
            ];
            $shipment = null;

            if ($status === 'cancelled') {
                $roll = mt_rand(1, 100);
                [$call, $note] = match (true) {
                    $roll <= 62 => ['cancelled', ['Customer cancelled on call', 'Ordered by mistake', 'Changed mind', 'Found it cheaper elsewhere', 'Duplicate order', null][mt_rand(0, 5)]],
                    $roll <= 80 => ['no_answer', 'No answer after 3 attempts'],
                    default => ['wrong_number', 'Number not reachable'],
                };
                $cancelledAt = Carbon::createFromTimestamp(max($calledAt->getTimestamp(), min($calledAt->getTimestamp() + mt_rand(0, 2880) * 60, $nowTs - 60)), 'UTC');
                $order = array_merge($order, [
                    'fulfillment_status' => 'cancelled',
                    'call_status' => $call,
                    'call_attempts' => $call === 'cancelled' ? 1 : 3,
                    'call_notes' => $note,
                    'tags' => json_encode(['Cancel']),
                    'cancelled_at' => $cancelledAt,
                ]);
                $order['updated_at'] = $order['cancelled_at'];
            } elseif ($status === 'unassigned') {
                $roll = mt_rand(1, 100);
                [$call, $attempts, $tag] = match (true) {
                    $roll <= 38 => ['confirmed', 1, 'Confirm'],
                    $roll <= 62 => ['not_called', 0, 'Pending'],
                    $roll <= 78 => ['no_answer', 1, 'No Answer 1st'],
                    $roll <= 92 => ['no_answer', 2, 'No Answer 2nd'],
                    default => ['no_answer', 3, 'No Answer 3rd'],
                };
                $order = array_merge($order, [
                    'call_status' => $call,
                    'call_attempts' => $attempts,
                    'last_called_at' => $attempts ? $order['last_called_at'] : null,
                    'tags' => json_encode([$tag]),
                ]);
                $order['updated_at'] = $order['last_called_at'] ?? $created;
            } else {
                $shipment = $this->shipment($status, $customer, $calledAt, $nowTs, $created);
                if ($status === 'delivered') {
                    $order = array_merge($order, [
                        'fulfillment_status' => 'fulfilled',
                        'financial_status' => 'paid',
                        'fulfilled_at' => $shipment['delivered_at'],
                        'paid_at' => $shipment['delivered_at'],
                    ]);
                    $settled[] = [$shipment['delivered_at'], $totalPrice, 'delivered'];
                } elseif ($status === 'returned') {
                    $order = array_merge($order, ['fulfillment_status' => 'cancelled', 'cancelled_at' => $shipment['updated_at']]);
                    $settled[] = [$shipment['updated_at'], 0, 'returned'];
                }
                $order['updated_at'] = $shipment['updated_at'];
                foreach ($items as &$item) {
                    $item['updated_at'] = $shipment['updated_at'];
                }
                unset($item);
            }

            $orders[] = ['order' => $order, 'items' => $items, 'shipment' => $shipment];
        }

        return [$orders, $this->payouts($settled, $start, $nowTs)];
    }

    /**
     * COD settled every two weeks: what was collected in the window, less
     * the store's delivery, return and call charges. The latest window is
     * still open, so the store is owed a balance like any live store.
     */
    private function payouts(array $settled, Carbon $start, int $nowTs): array
    {
        $payouts = [];
        $windowStart = $start->copy()->next(Carbon::SUNDAY);
        $ref = 41827;

        while ($windowStart->copy()->addDays(14)->getTimestamp() < $nowTs - 3 * 86400) {
            $windowEnd = $windowStart->copy()->addDays(14);
            $cod = 0;
            $fees = 0;
            foreach ($settled as [$at, $amount, $kind]) {
                if ($at >= $windowStart && $at < $windowEnd) {
                    $cod += $amount;
                    $fees += 25 + 1.5 + ($kind === 'returned' ? 10 : 0);
                }
            }
            if ($cod > 0) {
                $paidAt = $windowEnd->copy()->addDays(mt_rand(1, 3))->setTime(mt_rand(7, 12), mt_rand(0, 59));
                $payouts[] = [
                    'amount' => round($cod - $fees, 2),
                    'message' => sprintf('COD settlement %s – %s · Bank transfer ref %d', $windowStart->format('d M'), $windowEnd->copy()->subDay()->format('d M'), $ref += mt_rand(311, 977)),
                    'paid_at' => $paidAt,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ];
            }
            $windowStart = $windowEnd;
        }

        return $payouts;
    }

    private function customer(): array
    {
        $roll = mt_rand(1, 100);
        $name = match (true) {
            $roll <= 22 => self::AR_FIRST[array_rand(self::AR_FIRST)].' '.self::AR_FAMILY[array_rand(self::AR_FAMILY)],
            $roll <= 36 => self::EXPAT[array_rand(self::EXPAT)],
            $roll <= 70 => self::MALE[array_rand(self::MALE)].' '.self::FAMILY[array_rand(self::FAMILY)],
            default => self::FEMALE[array_rand(self::FEMALE)].' '.self::FAMILY[array_rand(self::FAMILY)],
        };
        if (mt_rand(1, 100) <= 6) {
            $name = mb_strtolower($name);
        }

        $city = $this->weighted(array_map(fn ($c) => $c[0], self::CITIES));
        [, $province, $zipBase, $districts] = self::CITIES[$city];
        $district = $districts[array_rand($districts)];
        $zip = $zipBase.str_pad((string) mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        $street = self::STREETS[array_rand(self::STREETS)];
        $address = match (mt_rand(1, 3)) {
            1 => mt_rand(2100, 9899).' '.$street.', '.$district,
            2 => strtoupper($street).', '.$district.', '.$city.' '.$zip,
            default => $district.' District, '.$street.', Building '.mt_rand(3, 89),
        };

        return [
            'name' => $name,
            'phone' => '+9665'.[0, 3, 4, 5, 6, 8, 9][mt_rand(0, 6)].mt_rand(1000000, 9999999),
            'city' => $city,
            'province' => $province,
            'zip' => $zip,
            'address' => $address,
            'district' => $district,
        ];
    }

    /**
     * A courier booking and its tracking history, shaped like what each
     * courier's own sync writes.
     */
    private function shipment(string $status, array $customer, Carbon $calledAt, int $nowTs, Carbon $created): array
    {
        $courier = $this->weighted(['imile' => 68, 'jnt_express' => 20, 'logestechs' => 12]);
        if ($status === 'attempt_fail' && $courier === 'imile') {
            $courier = mt_rand(0, 1) ? 'jnt_express' : 'logestechs';
        }

        $city = $customer['city'];
        [, , , , $stations, $hub] = self::CITIES[$city];
        $station = $stations[array_rand($stations)];
        $local = $city === 'Riyadh';

        // [step, minutes after the previous step]
        $path = [['submit', 0], ['pickup', mt_rand(120, 480)], ['hub_in', mt_rand(180, 600)], ['hub_out', mt_rand(120, 360)]];
        if ($hub) {
            $path[] = ['far_hub', mt_rand(600, 1800)];
        }
        $path[] = ['arrive', $local ? mt_rand(180, 420) : mt_rand(240, 900)];
        $path[] = ['dispatch', mt_rand(60, 240)];
        $toDoor = fn () => [['ofd', mt_rand(30, 150)]];
        $retry = fn () => [['fail', mt_rand(90, 480)], ['reschedule', mt_rand(20, 120)], ['ofd', mt_rand(960, 1500)]];

        switch ($status) {
            case 'info_received':
                // The steps each courier still reports as "info received".
                $path = match ($courier) {
                    'imile' => array_slice($path, 0, mt_rand(1, 2)),
                    'jnt_express' => [['pickup', 0]],
                    default => [['submit', 0]],
                };
                break;
            case 'in_transit':
                $path = array_slice($path, 0, mt_rand(3, count($path)));
                break;
            case 'out_for_delivery':
                $path = array_merge($path, $toDoor(), mt_rand(1, 100) <= 25 ? $retry() : []);
                break;
            case 'attempt_fail':
                $path = array_merge($path, $toDoor(), [['fail', mt_rand(90, 480)]]);
                break;
            case 'exception':
                $path = array_merge($path, $toDoor(), [['fail', mt_rand(90, 480)], ['exception', mt_rand(30, 300)]]);
                break;
            case 'delivered':
                $path = array_merge($path, $toDoor(), mt_rand(1, 100) <= 14 ? $retry() : [], [['delivered', mt_rand(45, 420)]]);
                break;
            case 'returned':
                $path = array_merge($path, $toDoor(), $retry(), [['fail', mt_rand(90, 480)], ['exception', mt_rand(30, 200)], ['return_ship', mt_rand(720, 2400)], ['return_arrive', mt_rand(480, 1440)], ['returned', mt_rand(600, 2000)]]);
                break;
        }

        // Booked a few hours after the confirmation call; the whole history
        // ends before now, squeezed in for the orders placed most recently.
        $span = array_sum(array_column($path, 1)) * 60 + count($path) * 60;
        $latestEnd = $nowTs - mt_rand(1800, 14400);
        $booked = $calledAt->getTimestamp() + mt_rand(120, 1500) * 60;
        if ($booked + $span > $latestEnd) {
            $booked = min($nowTs - 90, max($calledAt->getTimestamp() + 300, min($booked, $latestEnd - 3600)));
            $latestEnd = max($latestEnd, min($nowTs - 60, $booked + 600));
        }
        $scale = $booked + $span > $latestEnd ? max(0.0, ($latestEnd - $booked) / $span) : 1;
        $booked = Carbon::createFromTimestamp($booked, 'UTC');

        $at = $booked->copy();
        $events = [];
        $reason = self::FAIL_REASONS[array_rand(self::FAIL_REASONS)];
        $payment = mt_rand(1, 100) <= 62 ? 'Cash' : 'POS';
        foreach ($path as [$step, $minutes]) {
            $at = $at->copy()->addSeconds((int) (($minutes * 60 + mt_rand(0, 59)) * $scale));
            $event = $this->event($courier, $step, $city, $station, $hub, $reason, $payment, $at);
            if ($event) {
                $events[] = $event;
            }
        }
        $last = end($events);

        $tracking = $this->trackingNumber($courier, $booked);
        $history = array_reverse(array_map(fn ($e) => [
            'status' => $e['status'],
            'description' => $e['description'],
            'location' => $e['location'],
            'timestamp' => $e['timestamp'],
            'raw_status' => $e['raw'],
        ], $events));

        return [
            'courier' => $courier,
            'tracking_number' => $tracking,
            'sorting_code' => $courier === 'logestechs' ? substr($tracking, 5, 8) : null,
            'status' => $status,
            'courier_status' => $last['raw'],
            'courier_status_description' => $last['description'],
            'tracking_history' => json_encode($history, JSON_UNESCAPED_UNICODE),
            'weight' => [0.5, 0.5, 0.5, 1.0, 1.5][mt_rand(0, 4)],
            'service_type' => ['imile' => '02', 'logestechs' => 'STANDARD', 'jnt_express' => 'EZ'][$courier],
            'shipped_at' => $booked,
            'delivered_at' => $status === 'delivered' ? $last['at'] : null,
            'otp_verified' => false,
            'created_at' => $booked,
            'updated_at' => $last['at'],
        ];
    }

    private function event(string $courier, string $step, string $city, string $station, ?string $hub, string $reason, string $payment, Carbon $at): ?array
    {
        $ksa = $at->copy()->setTimezone('Asia/Riyadh');
        $tpl = match ($courier) {
            'imile' => match ($step) {
                'submit' => ['info_received', 'SubmitOrder', 'Order Submited.', null],
                'pickup' => ['info_received', 'AssignPickup', '【Riyadh Collection Station】Pick up task assigned to DA.', 'Riyadh Collection Station'],
                'hub_in' => ['in_transit', 'RecevieOrder', '【Riyadh Distribution Center】Received.', 'Riyadh Distribution Center'],
                'hub_out' => ['in_transit', 'Shipping', '【Riyadh Distribution Center】Parcel shipped to【'.($hub ? "{$hub} Distribution Center" : $station).'】.', 'Riyadh Distribution Center'],
                'far_hub' => ['in_transit', 'Shipping', "【{$hub} Distribution Center】Parcel shipped to【{$station}】.", "{$hub} Distribution Center"],
                'arrive' => ['in_transit', 'Arrive', "【{$station}】Arrived.", $station],
                'dispatch' => ['in_transit', 'AssignDA', "【{$station}】Dispatch task assigned to DA.", $station],
                'ofd' => ['out_for_delivery', 'OFD', "【{$station}】Shipment out for delivery.", $station],
                'fail' => ['exception', 'BTD', "【{$station}】 Back To DS.", $station],
                'reschedule' => ['in_transit', '3004', '<Scheduled Delivery>,scheduled to <'.$ksa->copy()->addDay()->format('Y-m-d').'>.', $station],
                'exception' => ['exception', 'BTD', "【{$station}】 Back To DS.", $station],
                'delivered' => ['delivered', 'Delivered', "【{$station}】Delivered,payment method <{$payment}>.", $station],
                'return_ship' => ['in_transit', 'ReturnShipping', '【'.($hub ? "{$hub} Distribution Center" : $station).'】Shipment returned to【Riyadh Return Center】.', $hub ? "{$hub} Distribution Center" : $station],
                'return_arrive' => ['in_transit', 'ReturnArrive', '【Riyadh Return Center】Shipment received.', 'Riyadh Return Center'],
                'returned' => ['returned', 'RTC', '【Riyadh Collection Station】Shipment returned to origin.', 'Riyadh Collection Station'],
            },
            'jnt_express' => match ($step) {
                'submit' => null,
                'pickup' => ['info_received', 'Pickup scan', '【Riyadh】The parcel has been picked up by [Riyadh Pickup Station]', 'Riyadh'],
                'hub_in' => ['in_transit', 'Arrival scan', 'The parcel has arrived at [Riyadh Sorting Center] station', 'Riyadh'],
                'hub_out' => ['in_transit', 'Departure scan', "【Riyadh】The parcel has departed from [Riyadh Sorting Center], next stop is [{$city} Station]", 'Riyadh'],
                'far_hub' => null,
                'arrive' => ['in_transit', 'Arrival scan', "The parcel has arrived at [{$city} Station] station", $city],
                'dispatch' => null,
                'ofd' => ['out_for_delivery', 'Delivery scan', "【{$city}】The parcel is out for delivery from [{$city} Station]", $city],
                'fail' => ['attempt_fail', 'Problematic scanning', "【{$city}】[{$city} Station] has operated abnormal parcel scaning, abnormal reason is [{$reason}]", $city],
                'reschedule' => null,
                'exception' => ['exception', 'Problematic scanning', "【{$city}】[{$city} Station] has operated abnormal parcel scaning, abnormal reason is [Customer requested return.]", $city],
                'delivered' => ['delivered', 'Sign scan', "【{$city}】The parcel has been signed by [Receiver signed], the Delivery courier is [920051211 or +9668001010111] ", $city],
                'return_ship' => ['in_transit', 'Return scan', "【{$city}】The parcel is being returned to the sender", $city],
                'return_arrive' => ['in_transit', 'Arrival scan', 'The parcel has arrived at [Central Pick Up Station] station', 'Riyadh'],
                'returned' => ['returned', 'Returned signed', 'The Shipment has been returned to the sender! If there is any problem or need to complain, Please call the Station phone：[920051211 or +9668001010111]', 'Riyadh'],
            },
            default => match ($step) {
                'submit' => ['info_received', 'APPROVED', 'Approved', null],
                'pickup' => ['in_transit', 'SCANNED_BY_DRIVER_AND_IN_CAR', 'Picked', 'Riyadh'],
                'hub_in', 'far_hub', 'dispatch', 'reschedule', 'return_arrive' => null,
                'hub_out' => ['in_transit', 'TRANSFERRED_OUT', "In transit to {$city}", 'Riyadh'],
                'arrive' => ['in_transit', 'SCANNED_BY_HANDLER_AND_UNLOADED', 'Arrived at hub', $city],
                'ofd' => ['out_for_delivery', 'OUT_FOR_DELIVERY', 'Out for delivery', $city],
                'fail' => ['attempt_fail', 'FAILED', 'Failed delivery attempt — '.rtrim($reason, '.'), $city],
                'exception' => ['exception', 'POSTPONED_DELIVERY', 'Postponed', $city],
                'delivered' => ['delivered', 'DELIVERED_TO_RECIPIENT', 'Delivered', $city],
                'return_ship' => ['in_transit', 'RETURNED_BY_RECIPIENT', 'Returned by recipient', $city],
                'returned' => ['returned', 'RETURNED_TO_SENDER', 'Returned to sender', 'Riyadh'],
            },
        };

        if (! $tpl) {
            return null;
        }

        return [
            'status' => $tpl[0],
            'raw' => $tpl[1],
            'description' => $tpl[2],
            'location' => $tpl[3],
            'at' => $at,
            'timestamp' => match ($courier) {
                'imile' => $at->copy()->setTimezone('+08:00')->format('Y-m-d\TH:i:sP'),
                'jnt_express' => $ksa->format('Y-m-d H:i:s'),
                default => $at->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:sP'),
            },
        ];
    }

    /** @var array<string, true> */
    private array $issued = [];

    /**
     * In ranges the couriers have already passed (iMile numbers carry their
     * date; J&T and Logestechs run upwards), so no real booking reuses one.
     */
    private function trackingNumber(string $courier, Carbon $booked): string
    {
        do {
            $number = match ($courier) {
                'imile' => '6'.$booked->copy()->setTimezone('Asia/Riyadh')->format('mdy').mt_rand(100000, 999999),
                'jnt_express' => 'JTE00101'.mt_rand(1000000, 9999999),
                default => 'NAV1004'.mt_rand(10000000, 99999999),
            };
        } while (isset($this->issued[$courier.$number])
            || DB::table('shipments')->where('courier', $courier)->where('tracking_number', $number)->exists());

        $this->issued[$courier.$number] = true;

        return $number;
    }

    /** Product photos, copied from the catalogue onto the public disk. */
    private function attachImages(int $clientId, array $catalogue): void
    {
        $products = DB::table('client_products')->where('client_id', $clientId)->orderBy('id')->pluck('id')->all();

        foreach ($catalogue as $i => $product) {
            if (! $product['image'] || ! isset($products[$i])) {
                continue;
            }
            try {
                $response = Http::timeout(20)->get($product['image']);
                if (! $response->successful()) {
                    continue;
                }
                $ext = match (true) {
                    str_contains((string) $response->header('Content-Type'), 'webp') => 'webp',
                    str_contains((string) $response->header('Content-Type'), 'png') => 'png',
                    str_contains((string) $response->header('Content-Type'), 'avif') => 'avif',
                    default => 'jpg',
                };
                $path = "client-products/{$products[$i]}/".Str::random(40).".{$ext}";
                Storage::disk('public')->put($path, $response->body());
                DB::table('client_product_images')->insert([
                    'client_product_id' => $products[$i],
                    'path' => $path,
                    'position' => 1,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            } catch (\Throwable $e) {
                $this->warn("  No photo for {$product['name']}: {$e->getMessage()}");
            }
        }
    }

    private function purge(string $name): int
    {
        $client = DB::table('clients')->where('company_name', $name)->where('is_isolated', true)->first();

        if (! $client) {
            $this->error("No isolated client named \"{$name}\".");

            return self::FAILURE;
        }

        if (! $this->confirm("Permanently delete \"{$name}\" and all its orders, shipments, products and payouts?", true)) {
            return self::FAILURE;
        }

        // Staff made for this store alone: assigned to it and to nothing else.
        $staff = DB::table('client_user_access')->where('client_id', $client->id)->pluck('user_id')
            ->filter(fn ($id) => DB::table('client_user_access')->where('user_id', $id)->count() === 1
                && DB::table('users')->where('id', $id)->value('client_access') === 'assigned')
            ->all();
        $users = array_values(array_unique([...$staff, (int) $client->user_id]));

        DB::transaction(function () use ($client, $users) {
            $orders = DB::table('orders')->where('client_id', $client->id)->pluck('id');
            foreach ($orders->chunk(1000) as $chunk) {
                DB::table('order_items')->whereIn('order_id', $chunk)->delete();
                DB::table('shipments')->whereIn('order_id', $chunk)->delete();
            }
            DB::table('orders')->where('client_id', $client->id)->delete();

            $products = DB::table('client_products')->where('client_id', $client->id)->pluck('id');
            DB::table('client_product_images')->whereIn('client_product_id', $products)->delete();
            DB::table('client_products')->where('client_id', $client->id)->delete();
            foreach ($products as $id) {
                Storage::disk('public')->deleteDirectory("client-products/{$id}");
            }

            DB::table('client_payments')->where('client_id', $client->id)->delete();
            DB::table('client_user_access')->where('client_id', $client->id)->delete();
            DB::table('clients')->where('id', $client->id)->delete();

            DB::table('model_has_roles')->where('model_type', \App\Models\User::class)->whereIn('model_id', $users)->delete();
            DB::table('client_user_access')->whereIn('user_id', $users)->delete();
            DB::table('users')->whereIn('id', $users)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info("Removed \"{$name}\" and everything in it.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int|string, float|int>  $weights
     */
    private function weighted(array $weights): int|string
    {
        $roll = mt_rand() / mt_getrandmax() * array_sum($weights);
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
