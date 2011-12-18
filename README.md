Migrace
=======

Napojeni na aplikaci
--------------------
V rootu aplikace si vytvořte složku `/migration` (například).
Do ní umístěte soubor `/example/run.php`.
Dle instrukcí v souboru `/example/run.php` nastavte proměnné `$productionMode` a `$dibi` způsobem který umožnuje vaše aplikace.


Práce s migracemi
-----------------

### Spuštění
Migrace spustíte v prohlížeči na adrese `/migration/run.php`.

Resetovat celou databázi (POZOR VŠE SMAŽE) můžete přes `/migration/run.php?reset`.

### Přidání nové
(Doporučená konvence pojmenování.)

Ve složce `/migration` vytvoříte soubor s příponou `*.sql` ve formátu `YYYY-MM-DD[-N][-description].sql`.

Description je volitelný ale doporučuje se ho psát. N je číslo pro určení pořádí v případě několika souboru ze stejným datumem.
Např. `2011-12-29.sql`, `2011-12-30-1-foo.sql`, `2011-12-30-2-bar.sql`, `2011-12-31-boo.sql`.

**Migrace se nesmí smazat ani editovat.**
