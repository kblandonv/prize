
// Función para obtener precios desde GoldAPI.io y almacenarlos en caché
function fetch_metal_prices() {
    $apiKey = "67b3b92555199f7f8a1b785e299582e8"; // Reemplaza con tu clave de API
    $symbols = ['XAU', 'XAG', 'XPT', 'XPD']; // Oro, Plata, Platino, Paladio
    $currency = "USD";
    $prices = [];

    foreach ($symbols as $symbol) {
        $url = "https://www.goldapi.io/api/{$symbol}/{$currency}";
        $headers = [
            'x-access-token: ' . $apiKey,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true);

        // Verificar si la API devuelve un precio válido
        if (!empty($data['price'])) {
            $prices[$symbol] = $data['price'];
        } else {
            // Registrar errores en el log de WordPress
            error_log("Error fetching price for {$symbol}: " . print_r($data, true));
        }
    }

    // Almacenar los precios en caché durante 1 día
    set_transient('goldapi_metal_prices', $prices, DAY_IN_SECONDS);

    return $prices;
}

// Función para recuperar precios almacenados en caché
function get_cached_metal_prices() {
    $prices = get_transient('goldapi_metal_prices');
    if (!$prices) {
        $prices = fetch_metal_prices(); // Si no hay datos en caché, obtén precios desde la API
    }
    return $prices;
}

// Configurar una tarea cron diaria para actualizar los precios
if (!wp_next_scheduled('update_daily_metal_prices')) {
    wp_schedule_event(time(), 'daily', 'update_daily_metal_prices');
}

// Acción vinculada a la tarea cron
add_action('update_daily_metal_prices', 'fetch_metal_prices');

// Mostrar los precios en un banner en la parte superior del sitio
add_action('wp_head', function() {
    $prices = get_cached_metal_prices();

    // Registrar el contenido del caché en el log
    error_log('Cached Prices: ' . print_r($prices, true));

    if ($prices) {
        echo '<div style="background: #f1c40f; color: #000; padding: 10px; text-align: center; font-weight: bold;">';
        echo 'Gold (XAU): $' . number_format($prices['XAU'], 2) . ' | ';
        echo 'Silver (XAG): $' . number_format($prices['XAG'], 2) . ' | ';
        echo 'Platinum (XPT): $' . number_format($prices['XPT'], 2) . ' | ';
        echo 'Palladium (XPD): $' . number_format($prices['XPD'], 2);
        echo '</div>';
    } else {
        echo '<div style="background: #f1c40f; color: #000; padding: 10px; text-align: center; font-weight: bold;">';
        echo 'Metal prices are currently unavailable. Please check back later.';
        echo '</div>';
    }
});


// Función para actualizar automáticamente los precios de los productos con márgenes específicos
add_action('init', function() {
    $prices = get_cached_metal_prices();
    
    // Márgenes específicos para cada metal
    $markup_percentages = [
        'XAU' => 10, // Oro: 10%
        'XAG' => 15, // Plata: 15%
        'XPT' => 12, // Platino: 12%
        'XPD' => 20  // Paladio: 20%
    ];

    if ($prices) {
        $products = wc_get_products(['limit' => -1]); // Obtener todos los productos

        foreach ($products as $product) {
            $sku = $product->get_sku(); // Usar SKU para asociar productos con metales
            if (isset($prices[$sku]) && isset($markup_percentages[$sku])) {
                $base_price = $prices[$sku];
                $markup_percentage = $markup_percentages[$sku];
                $new_price = $base_price * (1 + ($markup_percentage / 100));
                $product->set_regular_price($new_price);
                $product->save();
            }
        }
    }
});
