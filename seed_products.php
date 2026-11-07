<?php

// Csak terminálból futhat, böngészőből nem
if (PHP_SAPI !== 'cli') {
    exit('Ez a szkript csak terminálból futtatható.');
}

require_once __DIR__ . '/db.php';

$faker = Faker\Factory::create('en_US');

$categories = ['laptop', 'telefon', 'monitor', 'ssd', 'tartozék'];
$brands     = ['Acer', 'Asus', 'Dell', 'HP', 'Lenovo', 'Samsung', 'LG', 'Xiaomi'];

$insert = $pdo->prepare(
    'INSERT INTO products (name, price, description, category, created_at)
     VALUES (:name, :price, :description, :category, :created_at)'
);

$added = 0;

for ($i = 0; $i < 1000; $i++) {

    if ($i < 5) {
        $category = $categories[$i];
    } else {
        $category = $categories[array_rand($categories)];
    }

    $name = ucfirst($category) . ' '
          . $faker->randomElement($brands) . ' '
          . strtoupper($faker->bothify('??-###'));

    $price = $faker->randomFloat(2, 5, 2000);

    do {
        $description = $faker->paragraph(3);
    } while (mb_strlen($description) <= 60);

    $createdAt = $faker->dateTimeBetween('-12 months', 'now')->format('Y-m-d H:i:s');

    $insert->execute([
        ':name'        => $name,
        ':price'       => $price,
        ':description' => $description,
        ':category'    => $category,
        ':created_at'  => $createdAt,
    ]);
    $added++;
}

$total = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

echo 'Hozzáadott termékek száma: ' . $added . PHP_EOL;
echo 'Termékek száma a táblában összesen: ' . $total . PHP_EOL;