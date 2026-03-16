# Camp Availability Integration

**Version:** 1.3.79 | **Author:** Marc Mirschel | **Website:** [ayon.to](https://ayon.to)

---

## Beschreibung

WooCommerce-Plugin für **Camp-Buchungssysteme** mit nahtloser Integration des **Stachethemes Seat Planner**. Bietet Echtzeit-Verfügbarkeitsanzeige, Warenkorb-Reservierungen mit Countdown und eine professionelle Admin-Oberfläche.

---

## Hauptfunktionen

### Verfügbarkeit & Buchung
- **BuyBox** mit Echtzeit-Status (Verfügbar / Begrenzt / Ausgebucht) und nativer WC Add-to-Cart Integration
- **Kontextabhängige Labels** — erkennt automatisch Parzelle, Zimmer oder Bungalow anhand des Produktnamens
- **Warteliste** — E-Mail-Benachrichtigung bei ausgebuchten Produkten
- **Shortcode `[as_cai_availability]`** — 4 Display-Modi (badge, bar, text, count) für Shop-Loop und Elementor

### Warenkorb-Reservierung
- Automatische Reservierung beim Hinzufügen zum Warenkorb (konfigurierbar, Standard: 5 Min.)
- Globaler Countdown-Timer im Warenkorb
- Automatische Freigabe nach Ablauf

### Countdown-Timer
- Zeigt Countdown bis zum Verkaufsstart auf Produktseiten
- Blendet den Buchungs-Button erst nach Ablauf ein
- Konfigurierbare Position und Darstellung

### Stachethemes Integration
- Volle Seat Planner Integration (Parzellen-Auswahl via Modal)
- Farboverride auf Gold (#B19E63)
- Kontextabhängige Seat-Labels (Parzellen-ID / Zimmer-ID / Bungalow-ID)

---

## Installation

1. Plugin-ZIP hochladen oder nach `/wp-content/plugins/` extrahieren
2. Plugin im WordPress-Dashboard aktivieren
3. Benötigte Plugins sicherstellen:
   - **WooCommerce** (erforderlich)
   - **Stachethemes Seat Planner** (erforderlich)
   - Product Availability Scheduler / Koala Apps (optional, Fallback)

### Anforderungen

| Komponente | Version |
|-----------|---------|
| WordPress | 6.5+ |
| PHP | 8.0+ |
| WooCommerce | 9.5+ |
| Stachethemes Seat Planner | 1.0.22+ |

---

## Admin-Oberfläche

Erreichbar unter **WP-Admin → Ayonto Camp Avail.**

| Seite | Beschreibung |
|-------|-------------|
| **Dashboard** | Statistiken + Verfügbarkeitsübersicht nach Event (Kategorie) |
| **Reservierungen** | Liste aktiver Warenkorb-Reservierungen |
| **Reservierung anlegen** | Manuelle Parzellen-Reservierung mit Seat-Grid |
| **Shortcode Builder** | Generator mit Live-Vorschau und Copy-to-Clipboard |
| **Einstellungen** | Countdown, Warenkorb, Updates (3 Tabs) |
| **Entwickler** | Debug-Einstellungen, Debug-Tools, Test Suite (3 Tabs) |
| **Dokumentation** | README, Latest Update, Changelog, Hilfe & Systeminfo |

---

## Shortcodes

### `[as_cai_availability]`
Zeigt Verfügbarkeitsstatus im Shop-Loop oder auf Produktseiten.

```
[as_cai_availability]
[as_cai_availability product_id="123" display="bar"]
```

| Parameter | Werte | Standard |
|-----------|-------|----------|
| `product_id` | Produkt-ID (optional im Loop) | aktuelles Produkt |
| `display` | `badge`, `bar`, `text`, `count` | `badge` |

### `[as_cai_availability_counter]`
Countdown-Timer manuell platzieren (z.B. in Elementor Shortcode-Widget).

### `[as_cai_order_confirmation]`
Bestelldetails auf der Bestätigungsseite inkl. Seat Planner Daten.

```
[as_cai_order_confirmation]
[as_cai_order_confirmation title="Bestellübersicht" show_customer_details="no"]
```

### `[as_cai_buybox]`
BuyBox mit Preis, Verfügbarkeitsstatus und Add-to-Cart Button.

---

## Konfiguration

### Warenkorb-Reservierungen
**Einstellungen → Warenkorb**
- Reservierung aktivieren/deaktivieren
- Reservierungszeit (1–60 Minuten)
- Timer-Darstellung (Vollständig / Kompakt / Minimal)
- Warnschwelle konfigurieren

### Countdown-Timer
**Einstellungen → Countdown**
- Timer aktivieren/deaktivieren
- Position (vor/nach Warenkorb-Button, vor Produkt-Meta)
- Darstellung (Standard / Minimal / Fett)
- Shortcode-Countdown Template-Builder mit Live-Vorschau

### Updates
**Einstellungen → Updates**
- Direkte Update-Prüfung gegen GitHub
- Version-Switcher mit Rollback-Möglichkeit
- Ein-Klick-Installation

---

## Technische Details

### Datenquellen

| Produkttyp | Datenquelle | Methode |
|-----------|-------------|---------|
| Auditorium (Parzellen) | Stachethemes Seat Plan JSON | `get_seat_plan_data()` + `get_taken_seats()` |
| Simple (mit Stock) | WooCommerce Stock | `get_stock_quantity()` + Order-Counting |

### Datenbank-Tabellen

| Tabelle | Zweck |
|---------|-------|
| `wp_as_cai_reservations` | Warenkorb-Reservierungen |
| `wp_as_cai_admin_reservations` | Manuelle Admin-Reservierungen |
| `wp_as_cai_notifications` | Warteliste (E-Mail-Benachrichtigungen) |

### WordPress-Rolle
**Camp Manager** — Zugriff auf WooCommerce, Seat Planner und Plugin-Admin ohne volle Admin-Rechte.

### Auto-Update
GitHub-basierter Updater (`zb-marc/Camp-Availability-Integration`). Prüft Releases automatisch, unterstützt private Repos via `AS_CAI_GITHUB_TOKEN`.

---

## Kompatibilität

| Plugin/Theme | Version |
|-------------|---------|
| Elementor Pro | 3.32.3+ |
| WooCommerce | 10.3.3+ |
| Stachethemes Seat Planner | 1.0.22+ |
| Product Availability Scheduler | 1.0.2+ |
| WordPress | 6.8.3+ |
| PHP | 8.3+ |

---

## Support

- **E-Mail:** kundensupport@zoobro.de
- **Website:** [ayon.to](https://ayon.to)
- **Entwickler:** Marc Mirschel ([marc.mirschel.biz](https://marc.mirschel.biz))

## Lizenz

GNU General Public License v2 oder höher — [gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html)

---

**Powered by [ayon.to](https://ayon.to)**
