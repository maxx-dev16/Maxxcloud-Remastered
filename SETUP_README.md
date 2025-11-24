# MaxxCloud Main Bucket Setup

## SQL-Script ausführen

Das SQL-Script erstellt alle notwendigen Tabellen für den Main Bucket (Bucket-1).

### Voraussetzungen

- Datenbank: `u120_2Y9MEq18EI` (oder wie in `buckets.php` konfiguriert)
- User: `u120_2Y9MEq18EI`
- Host: `db.novium.world:3306`

### Ausführung

**Empfohlen: Verwende `setup_main_bucket_simple.sql` (einfacher)**

1. **Via phpMyAdmin:**
   - Öffne phpMyAdmin
   - Wähle die Datenbank `u120_2Y9MEq18EI`
   - Gehe zum Tab "SQL"
   - Kopiere den Inhalt von `setup_main_bucket_simple.sql`
   - Füge ihn ein und klicke auf "Ausführen"
   - **Wichtig:** Ignoriere Fehler wie "Duplicate column name" - das bedeutet nur, dass die Spalte bereits existiert

2. **Via MySQL Command Line:**
   ```bash
   mysql -h db.novium.world -P 3306 -u u120_2Y9MEq18EI -p u120_2Y9MEq18EI < setup_main_bucket_simple.sql
   ```

3. **Via MySQL Workbench:**
   - Verbinde mit der Datenbank
   - Öffne `setup_main_bucket_simple.sql`
   - Führe das Script aus
   - Ignoriere Fehler bei bereits existierenden Spalten

### Erstellte Tabellen

Das Script erstellt folgende Tabellen:

1. **users** - Benutzerdaten
2. **folders** - Ordnerstruktur
3. **files** - Dateimetadaten
4. **file_data** - Dateiinhalte (Storage)
5. **user_buckets** - User-Bucket-Zuordnung (für Hauptdatenbank)

### Nach der Ausführung

Nach dem Ausführen des Scripts sollte die Registrierung funktionieren. Teste die Registrierung über die Web-Oberfläche.

### Fehlerbehebung

Falls Fehler auftreten:
- Prüfe, ob der Datenbankname korrekt ist
- Prüfe, ob der User die notwendigen Rechte hat
- Prüfe die Fehlerprotokolle in der PHP error_log
- **Wichtig:** Fehler wie "Duplicate column name" können ignoriert werden - die Spalte existiert bereits
