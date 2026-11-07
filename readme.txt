Termékkatalógus

Telepítés:
1. composer install
2. schema.sql importálása phpMyAdminban (vagy: mysql -u root -p < schema.sql)
3. .env.example másolása .env néven, és a saját adatbázis adatok beírása
4. php seed_products.php (terminálból, minden futtatás 1000 terméket ad hozzá)
5. böngészőben: http://localhost/termekkatalogus/search_products.php

Tesztelés:
- szűrők nélkül: 1. oldal, 20 sor, a statisztika az összes termékre számol - jó
- név + kategória + ár (Dell, laptop, 100-500): minden találat megfelel - jó
- csak min / csak max ár: működik - jó
- min ár 0: elfogadja - jó
- min > max, betű vagy negatív szám az árban: hibaüzenet, nem keres - jó
- nem létező név: "Nincs a megadott feltételeknek megfelelő termék", 0 db, nincs warning - jó
- következő oldal: a szűrők és a rendezés megmaradnak, a statisztika ugyanaz - jó
- O'Brien név: nincs SQL hiba - jó
- <b>Test</b> nevű termék: szövegként jelenik meg, nem félkövér - jó
- seed újrafuttatása: 1000 -> 2000 -> 3000 - jó

A statisztika azért LIMIT nélkül készül, mert az összes találatra kell számolni,
nem csak az aktuális oldal 20 termékére.

A rendezés azért engedélyezett listából jön, mert az oszlopnevet és az irányt nem
lehet PDO paraméterként átadni, és ha a kérésből egyenesen az SQL-be kerülne,
SQL injection lehetne belőle.
