# Latest Update — v1.3.79

**Release:** 2026-03-16 | **Typ:** Feature | **Priority:** Empfohlen

---

## Zusammenfassung

Admin-Seite komplett umstrukturiert + neues **Verfügbarkeits-Dashboard** mit Echtzeit-Anzeige aller Produkte gruppiert nach Event.

---

## Admin Restructuring

### Neues Menü

| # | Seite | Inhalt |
|---|-------|--------|
| 1 | Dashboard | Statistiken + Verfügbarkeit je Event |
| 2 | Reservierungen | Warenkorb-Reservierungen |
| 3 | Reservierung anlegen | Manuelle Seat-Reservierung |
| 4 | Shortcode Builder | Generator mit Live-Vorschau |
| 5 | Einstellungen | Countdown, Warenkorb, Updates |
| 6 | Entwickler | Debug, Debug-Tools, Tests |
| 7 | Dokumentation | README, Changelog, Hilfe |

- Alle 7 Seiten als Header-Tabs sichtbar (vorher fehlten 2)
- Debug/Tests aus Einstellungen nach "Entwickler" verschoben

---

## Verfügbarkeits-Dashboard

Neue Sektion auf dem Dashboard zeigt die Verfügbarkeit aller aktiven Produkte:

- Gruppiert nach **Event** (= Produktkategorie)
- Je Produkt: Name, Mini-Progressbar, x/y Verfügbarkeit, Status-Badge
- Summenzeile pro Event mit Prozent-Badge
- Vergangene Events automatisch ausgeblendet

### Status-Labels

| Status | Label | Bedeutung |
|--------|-------|-----------|
| `available` | Verfügbar | Noch viele Plätze frei |
| `limited` | Gut gebucht | Über 50% gebucht |
| `critical` | Fast ausgebucht | Wenige Plätze frei |
| `reserved_full` | Voll reserviert | Alles reserviert |
| `sold_out` | Ausgebucht | Keine Plätze mehr |

---

## Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `includes/class-as-cai-admin.php` | Menü, Tabs, Dashboard, Entwickler-Tab |
| `as-camp-availability-integration.php` | Version 1.3.79 |
| `README.md` | Komplett überarbeitet |
| `CHANGELOG.md` | v1.3.79 Eintrag |

---

## Vorherige Versionen

| Version | Datum | Beschreibung |
|---------|-------|--------------|
| 1.3.78 | 2026-03-16 | BuyBox, kontextabh. Labels, Stach Gold, Shortcode, Reservierungsverwaltung |
| 1.3.77 | 2026-03-15 | Dual-Source Datenarchitektur (Auditorium + Simple) |
| 1.3.76 | 2026-03-15 | Warteliste mit Modal |
| 1.3.75 | 2026-03-15 | Seat Plan als primäre Datenquelle |
| 1.3.72 | 2026-03-15 | Admin UI: Ayonto Brand Identity |
| 1.3.68 | 2026-03-14 | PDF-Druck (Landscape) |
| 1.3.67 | 2026-03-14 | In-App Updater |
| 1.3.65 | 2026-03-13 | Settings auf Deutsch |
| 1.3.63 | 2026-03-13 | Server-side Availability Gate |
| 1.3.60 | 2026-03-12 | Rebranding: Battleground nach Ayonto |
| 1.3.59 | 2026-03-11 | Status Display + GitHub Updater |

> Vollständige Historie: siehe CHANGELOG.md
