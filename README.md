# MITS Language Files Define Fixer für modified eCommerce Shopsoftware
(c) Copyright 2025 by Hetfield – MerZ IT-SerVice

- Author: Hetfield – https://www.merz-it-service.de  
- Version: ab modified eCommerce Shopsoftware Version 2.x  

<hr />

## Beschreibung

Das Modul **MITS Language Files Define Fixer** dient dazu, Sprachdateien der
modified eCommerce Shopsoftware automatisiert zu überprüfen und anzupassen.

In vielen Shops werden zusätzliche oder eigene Sprachdateien verwendet,
insbesondere über den Mechanismus `auto_include()`, z.B. in den Verzeichnissen:

- `lang/german/extra/`
- `lang/english/extra/`

Dabei kommt es sehr häufig vor, dass Sprachkonstanten mehrfach definiert werden,
weil identische Konstanten sowohl in Core-Sprachdateien als auch in eigenen
Sprachdateien oder Modulen existieren.

PHP reagiert darauf mit Meldungen wie:

- `Notice: Constant XYZ already defined`
- `Warning: Constant XYZ already defined`

Diese Meldungen führen nicht nur zu unschönen Hinweisen im Frontend oder Backend,
sondern vor allem zu einer massiven Aufblähung der Log-Files.

Das Modul **MITS Language Files Define Fixer** behebt dieses Problem, indem es
alle Sprachdateien im Sprachordner rekursiv durchsucht und klassische
`define()`-Anweisungen automatisch in die sichere Kurzschreibweise umwandelt:

    defined('KONSTANTE') || define('KONSTANTE', 'Text');

Dadurch wird eine Konstante nur dann definiert, wenn sie zuvor noch nicht
existiert.

---

## Erweiterter Funktionsumfang

Zusätzlich zum klassischen `defined()`-Fix unterstützt das Modul nun auch den
**Vergleich und die Vervollständigung von Sprachdateien** in allen vorhandenen
Sprachordnern.

### Vergleich von Sprachordnern (außer `german` und `english`)

Alle Sprachordner **außer `german` und `english`** können automatisch geprüft
und mit einer **Referenzsprache** verglichen werden.

**Referenzsprache:**
- `german` oder
- `english` (wählbar)

**Prüfungen:**
- Vergleich jeder Sprachdatei mit der entsprechenden Datei der Referenzsprache
- Erkennung fehlender Sprachkonstanten
- Strukturierte Ausgabe der Ergebnisse:
    - Sprache
    - Datei
    - fehlende Konstanten

---

### Automatisches Ergänzen fehlender Konstanten (optional)

Optional können erkannte fehlende Konstanten automatisch ergänzt werden:

- Die Inhalte werden aus der Referenzsprache übernommen
- Neue Einträge werden immer sicher angelegt mit:

      defined('CONSTANT_NAME') || define('CONSTANT_NAME', 'Wert');

- Bestehende Konstanten und Dateien bleiben unverändert

---

### Umgang mit komplett fehlenden Sprachdateien (optional)

Fehlt eine Sprachdatei vollständig in einer Zielsprache, kann das Modul diese
automatisch behandeln:

- Die komplette Datei wird aus der Referenzsprache übernommen
- Anschließend wird automatisch der `defined()`-Guard-Fix angewendet
- Dieses Verhalten ist optional und kann ein- oder ausgeschaltet werden

---

### Korrekte Behandlung sprachabhängiger Dateinamen

In jedem Sprachordner existieren in der Regel sprachabhängige Hauptdateien,
z.B.:

- `lang/german/german.php`
- `lang/german/admin/german.php`

Das Modul berücksichtigt diese Besonderheit korrekt:

**Beispiel:**
- Fehlt `lang/french/french.php`
- Dann wird der **Inhalt** aus `lang/german/german.php` (oder `english.php`)
  übernommen
- Die **Zieldatei wird korrekt als `french.php` angelegt**
- Gleiches gilt für `admin/french.php`

Damit wird verhindert, dass Dateien mit falschen Namen (z.B. `german.php`) in
anderen Sprachordnern landen.

---

### Ergebnisübersicht / Dry-Run

Das Modul kann wahlweise:

- nur eine **Übersicht der gefundenen Probleme ausgeben**
- oder **automatisch korrigierend eingreifen**

Die Ergebnisliste enthält u.a.:

- fehlende Dateien
- neu angelegte Dateien
- ergänzte Sprachkonstanten

---

### Vorteile auf einen Blick

- verhindert doppelte Definitionen von Sprachkonstanten
- reduziert Notices und Warnings in Log-Files
- ergänzt fehlende Sprachkonstanten automatisch
- erkennt und kopiert fehlende Sprachdateien
- korrekte Behandlung sprachspezifischer Dateinamen
- sorgt für saubere und vollständige Sprachordner
- ideal für Shops mit vielen Modulen und auto_include()-Mechanismen
- einmalige Ausführung, keine dauerhafte Belastung des Systems

<hr />

Die Installation erfolgt ohne das Überschreiben von Dateien.

<hr />

## Lizenzinformationen

Diese Erweiterung ist unter der GNU/GPL lizensiert.  
Eine Kopie der Lizenz liegt diesem Modul bei oder kann unter der URL  
http://www.gnu.org/licenses/gpl-2.0.txt heruntergeladen werden.

Die Copyrighthinweise müssen erhalten bleiben bzw. mit eingebaut werden.
Zuwiderhandlungen verstoßen gegen das Urheberrecht und die GPL und werden
zivil- und strafrechtlich verfolgt!

<hr />

## Anleitung für das Modul MITS Language Files Define Fixer

### Installation

**Systemvoraussetzung:**  
Funktionsfähige modified eCommerce Shopsoftware ab Version 2.x

Vor der Installation des Moduls sichern Sie bitte vollständig Ihre aktuelle
Shopinstallation (Dateien und Datenbank)!  
Für eventuelle Schäden übernehmen wir keine Haftung.

Die Installation und Nutzung des Moduls **MITS Language Files Define Fixer**
erfolgt auf eigene Gefahr!

Die Installation des Moduls ist einfach und erfolgt in wenigen Schritten:

1. Führen Sie zuerst eine vollständige Sicherung des Shops durch.
   Sichern Sie dabei sowohl die Datenbank als auch alle Dateien Ihrer Shopinstallation.

2. Falls der Admin-Ordner des Shops umbenannt wurde, benennen Sie vor dem Hochladen
   auch den Ordner *admin* im Verzeichnis *shoproot* des Moduls entsprechend um.

3. Kopieren Sie anschließend alle Dateien aus dem Verzeichnis *shoproot* des Modulpakets
   in das Hauptverzeichnis Ihrer bestehenden modified eCommerce Shopsoftware Installation.
   Es werden dabei keine Dateien überschrieben.

4. Fertig. 

Sie finden den Link zum Modul im Menü des Adminbereichs unter (Adminrechte für module_export benötigt!)
      **Hilfsprogramme** -> **MITS Language Files Define Fixer**

<hr />

### Wichtiger Hinweis zur Nutzung

Das Modul nimmt **direkte Änderungen an Sprachdateien** vor.  
Ein vorheriges Backup des gesamten Sprachordners ist daher **zwingend erforderlich**.

Die Anpassung erfolgt automatisiert und kann sehr viele Dateien und Zeilen betreffen.
Eine manuelle Kontrolle einzelner Dateien ist im Normalfall nicht notwendig,
aber jederzeit möglich.

<hr />

Wir hoffen, das Modul **MITS Language Files Define Fixer** für die modified eCommerce Shopsoftware
gefällt Ihnen!

Benötigen Sie Unterstützung bei der individuellen Anpassung des Moduls oder haben Sie
Probleme beim Einbau, können Sie gerne unseren kostenpflichtigen Support in Anspruch nehmen.

Kontaktieren Sie uns einfach unter  
https://www.merz-it-service.de/Kontakt.html

<hr />

<img src="https://www.merz-it-service.de/images/logo.png" alt="MerZ IT-SerVice" title="MerZ IT-SerVice" />

**MerZ IT-SerVice**  
Nicole Grewe  
Am Berndebach 35a  
D-57439 Attendorn  

Telefon: 0 27 22 – 63 13 63  
Telefax: 0 27 22 – 63 14 00  

E-Mail: Info(at)MerZ-IT-SerVice.de  
Internet: https://www.MerZ-IT-SerVice
