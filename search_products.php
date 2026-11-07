<?php

require_once __DIR__ . '/db.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatPrice($value)
{
    return number_format((float) $value, 2, '.', ' ') . ' EUR';
}

function shortDescription($text)
{
    if (mb_strlen($text, 'UTF-8') > 60) {
        return mb_substr($text, 0, 60, 'UTF-8') . '…';
    }
    return $text;
}

function getParam($key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    if (!is_string($value)) {
        return $default;
    }
    return trim($value);
}

function pageUrl($input, $page)
{
    $input['page'] = $page;
    return '?' . http_build_query($input);
}

if (!extension_loaded('mbstring')) {
    exit('A PHP mbstring bővítménye nincs bekapcsolva (php.ini: extension=mbstring).');
}

const PER_PAGE = 20;

$categories = ['laptop', 'telefon', 'monitor', 'ssd', 'tartozék'];

// Rendezés: a kérésből csak a kulcs jön, az SQL innen
$sortOptions = [
    'name_asc'   => ['label' => 'Név szerint (A–Z)',   'sql' => 'name ASC, id ASC'],
    'price_asc'  => ['label' => 'Ár szerint növekvő',  'sql' => 'price ASC, id ASC'],
    'price_desc' => ['label' => 'Ár szerint csökkenő', 'sql' => 'price DESC, id ASC'],
];

$input = [
    'name'      => getParam('name'),
    'min_price' => getParam('min_price'),
    'max_price' => getParam('max_price'),
    'category'  => getParam('category'),
    'sort'      => getParam('sort', 'name_asc'),
];

if (!array_key_exists($input['sort'], $sortOptions)) {
    $input['sort'] = 'name_asc';
}

$errors = [];
$pricePattern = '/^\d+(\.\d+)?$/';

$minPrice = null;
$maxPrice = null;

if ($input['min_price'] !== '') {
    if (preg_match($pricePattern, $input['min_price'])) {
        $minPrice = (float) $input['min_price'];
    } else {
        $errors[] = 'A minimális ár csak nemnegatív szám lehet, tizedesponttal (pl. 49.99).';
    }
}

if ($input['max_price'] !== '') {
    if (preg_match($pricePattern, $input['max_price'])) {
        $maxPrice = (float) $input['max_price'];
    } else {
        $errors[] = 'A maximális ár csak nemnegatív szám lehet, tizedesponttal (pl. 499.99).';
    }
}

if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    $errors[] = 'A minimális ár nem lehet nagyobb a maximális árnál.';
}

if ($input['category'] !== '' && !in_array($input['category'], $categories, true)) {
    $errors[] = 'A kiválasztott kategória nem létezik.';
}

$stats      = ['cnt' => 0, 'min_price' => null, 'max_price' => null, 'avg_price' => null];
$products   = [];
$page       = 1;
$totalPages = 0;

if (!$errors) {

    $conditions = [];
    $params     = [];

    if ($input['name'] !== '') {
        $conditions[]    = 'name LIKE :name';
        $params[':name'] = '%' . $input['name'] . '%';
    }

    if ($minPrice !== null && $maxPrice !== null) {
        $conditions[]         = 'price BETWEEN :min_price AND :max_price';
        $params[':min_price'] = $input['min_price'];
        $params[':max_price'] = $input['max_price'];
    } elseif ($minPrice !== null) {
        $conditions[]         = 'price >= :min_price';
        $params[':min_price'] = $input['min_price'];
    } elseif ($maxPrice !== null) {
        $conditions[]         = 'price <= :max_price';
        $params[':max_price'] = $input['max_price'];
    }

    if ($input['category'] !== '') {
        $conditions[]        = 'category = :category';
        $params[':category'] = $input['category'];
    }

    $where = '';
    if ($conditions) {
        $where = 'WHERE ' . implode(' AND ', $conditions);
    }

    try {
        $statStmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt, MIN(price) AS min_price,
                    MAX(price) AS max_price, AVG(price) AS avg_price
             FROM products $where"
        );
        $statStmt->execute($params);
        $stats = $statStmt->fetch();

        $totalCount = (int) $stats['cnt'];
        $totalPages = (int) ceil($totalCount / PER_PAGE);

        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($page === false) {
            $page = 1;
        }
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        if ($totalCount > 0) {
            $orderBy = $sortOptions[$input['sort']]['sql'];

            $listStmt = $pdo->prepare(
                "SELECT id, name, price, category, description, created_at
                 FROM products $where
                 ORDER BY $orderBy
                 LIMIT :limit OFFSET :offset"
            );

            foreach ($params as $key => $value) {
                $listStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $listStmt->bindValue(':limit', PER_PAGE, PDO::PARAM_INT);
            $listStmt->bindValue(':offset', ($page - 1) * PER_PAGE, PDO::PARAM_INT);

            $listStmt->execute();
            $products = $listStmt->fetchAll();
        }
    } catch (PDOException $e) {
        $errors[] = 'Hiba történt a keresés közben. Próbáld újra később.';
    }
}

$hasResults = !$errors && (int) $stats['cnt'] > 0;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Termékkatalógus</title>
    <style>
        body  { font-family: Arial, sans-serif; max-width: 1100px; margin: 20px auto; padding: 0 10px; }
        form  { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; background: #f2f4f6; padding: 12px; }
        label { display: flex; flex-direction: column; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #ddd; padding: 6px; text-align: left; }
        th    { background: #e8ecef; }
        .errors { background: #fdecea; color: #8a1c17; padding: 10px; margin-top: 10px; }
        .stats  { display: flex; gap: 20px; font-weight: bold; margin-top: 15px; }
        .pager  { display: flex; gap: 15px; margin-top: 15px; }
    </style>
</head>
<body>

<h1>Termékkatalógus</h1>

<form method="get" action="search_products.php">

    <label>Terméknév:
        <input type="text" name="name" value="<?= e($input['name']) ?>">
    </label>

    <label>Min. ár (EUR, tizedespont):
        <input type="text" name="min_price" value="<?= e($input['min_price']) ?>" placeholder="pl. 10.50">
    </label>

    <label>Max. ár (EUR, tizedespont):
        <input type="text" name="max_price" value="<?= e($input['max_price']) ?>" placeholder="pl. 999.99">
    </label>

    <label>Kategória:
        <select name="category">
            <option value="">Összes kategória</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $input['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Rendezés:
        <select name="sort">
            <?php foreach ($sortOptions as $key => $option): ?>
                <option value="<?= e($key) ?>" <?= $input['sort'] === $key ? 'selected' : '' ?>><?= e($option['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <button type="submit">Keresés</button>
    <a href="search_products.php">Szűrők törlése</a>
</form>

<?php if ($errors): ?>

    <div class="errors">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <div class="stats">
        <span>Találatok: <?= (int) $stats['cnt'] ?> db</span>
        <span>Legolcsóbb: <?= $hasResults ? e(formatPrice($stats['min_price'])) : '—' ?></span>
        <span>Legdrágább: <?= $hasResults ? e(formatPrice($stats['max_price'])) : '—' ?></span>
        <span>Átlagár: <?= $hasResults ? e(formatPrice($stats['avg_price'])) : '—' ?></span>
    </div>

    <?php if (!$hasResults): ?>

        <p>Nincs a megadott feltételeknek megfelelő termék.</p>
        <p>0 oldal</p>

    <?php else: ?>

        <table>
            <tr>
                <th>Név</th>
                <th>Ár</th>
                <th>Kategória</th>
                <th>Leírás</th>
                <th>Létrehozva</th>
            </tr>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e(formatPrice($p['price'])) ?></td>
                    <td><?= e($p['category']) ?></td>
                    <td><?= e(shortDescription($p['description'])) ?></td>
                    <td><?= e($p['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="pager">
            <?php if ($page > 1): ?>
                <a href="<?= e(pageUrl($input, $page - 1)) ?>">« Előző</a>
            <?php endif; ?>

            <span><?= $page ?>. oldal / <?= $totalPages ?> oldal</span>

            <?php if ($page < $totalPages): ?>
                <a href="<?= e(pageUrl($input, $page + 1)) ?>">Következő »</a>
            <?php endif; ?>
        </div>

    <?php endif; ?>
<?php endif; ?>

</body>
</html>