# 🚀 Valcode Appoint v0.6.0 - Quick Start

## Was ist neu?

### 🎨 Schöneres Design
- Modern UI mit sanften Animationen
- Bessere Mobile-Ansicht
- Professionelles Calendar-Widget

### 📅 Kalender statt Datepicker
- Immer ausgeklappt und sichtbar
- Intuitive Auswahl
- Vergangene Tage automatisch deaktiviert

### ⚙️ Neue Einstellungen
- Mindest-Vorlaufzeit für Buchungen konfigurierbar
- Verhindert Last-Minute-Buchungen

### 🚫 Smart Slot-Blocking
- Gebuchte Zeitslots werden automatisch deaktiviert
- Visuelles Feedback für verfügbare/gebuchte Zeiten

---

## 🔧 Installation in 3 Schritten

### 1. Dateien ersetzen

```bash
# CSS ersetzen
assets/css/public.css → Ersetze mit public.css

# JavaScript ersetzen  
assets/js/appoint.js → Ersetze mit appoint.js

# PHP aktualisieren
valcode-appoint.php → Siehe IMPLEMENTATION_GUIDE.md
```

### 2. Plugin reaktivieren

```
WordPress Admin → Plugins → Valcode Appoint
→ Deaktivieren
→ Aktivieren
```

### 3. Einstellungen konfigurieren

```
WordPress Admin → Valcode Appoint → Einstellungen
→ Mindest-Vorlaufzeit einstellen (z.B. 1 Tag)
→ Speichern
```

---

## 📁 Datei-Übersicht

| Datei | Beschreibung | Änderung |
|-------|-------------|----------|
| `public.css` | Frontend-Styling | ✅ Komplett neu |
| `appoint.js` | Frontend-Logik | ✅ Komplett neu |
| `IMPLEMENTATION_GUIDE.md` | PHP-Änderungen | 📖 Anleitung |
| `VISUAL_CHANGES.md` | Visuelle Übersicht | 📖 Dokumentation |

---

## 🎯 Quick-Test

Nach der Installation:

1. **Frontend testen:**
   - Gehe zur Seite mit `[valcode_appoint]`
   - Kalender sollte in Step 2 ausgeklappt sein
   - Vergangene Tage sollten grau/disabled sein

2. **Admin testen:**
   - Neue Menü-Option "Einstellungen" sollte sichtbar sein
   - Mindest-Vorlaufzeit sollte einstellbar sein

3. **Booking testen:**
   - Buche einen Termin
   - Gehe zurück zum gleichen Tag/Zeit
   - Dieser Slot sollte jetzt disabled sein

---

## 🐛 Troubleshooting

### Kalender wird nicht angezeigt
- ✅ Browser-Cache leeren
- ✅ `appoint.js` korrekt ersetzt?
- ✅ Console-Fehler prüfen (F12)

### Alte Date-Input noch sichtbar
- ✅ PHP-Datei korrekt aktualisiert?
- ✅ Shortcode-Änderung durchgeführt?

### Slots werden nicht als gebucht angezeigt
- ✅ `ajax_get_slots()` Methode aktualisiert?
- ✅ Datenbank-Termine vorhanden?

### Design sieht anders aus
- ✅ `public.css` korrekt ersetzt?
- ✅ Browser-Cache geleert?
- ✅ Inline-Styles in PHP korrekt?

---

## 📞 Support

Bei Fragen oder Problemen:
1. Prüfe `IMPLEMENTATION_GUIDE.md` für detaillierte Anweisungen
2. Schaue in `VISUAL_CHANGES.md` für visuelle Referenzen
3. Kontaktiere [Valcode Support](https://valcode.ch)

---

## 🔄 Rollback

Falls etwas schief geht:
1. Behalte Backups der alten Dateien
2. Ersetze neue Dateien mit alten Versionen
3. Plugin deaktivieren & reaktivieren

---

## ⭐ Features im Detail

### Kalender-Widget
- ✅ Monatliche Navigation (◀ ▶)
- ✅ Heute-Markierung (blaue Border)
- ✅ Auswahl-Markierung (blauer Hintergrund)
- ✅ Automatische Validierung (Vergangenheit + Vorlaufzeit)
- ✅ Responsive für Mobile

### Slot-System
- ✅ Verfügbare Slots: Weiß, klickbar, Hover-Effekt
- ✅ Gebuchte Slots: Grau, disabled, kein Hover
- ✅ Ausgewählter Slot: Blau, hervorgehoben
- ✅ Echtzeit-Check vor Buchung

### Admin-Settings
- ✅ Mindest-Vorlaufzeit: 0-365 Tage
- ✅ Wird im Frontend automatisch berücksichtigt
- ✅ Verhindert ungültige Buchungen

---

## 📊 Kompatibilität

- ✅ WordPress 5.0+
- ✅ PHP 7.4+
- ✅ Alle modernen Browser
- ✅ Mobile & Desktop
- ✅ Touch-optimiert

---

## 🚀 Performance

- Keine zusätzlichen Bibliotheken
- Vanilla JavaScript (kein jQuery mehr nötig)
- CSS-Animationen statt JS
- Optimierte AJAX-Calls
- Minimale DOM-Manipulation

---

## 🎉 Fertig!

Nach der Installation sollte dein Booking-System:
- ✨ Schöner aussehen
- 🚀 Besser funktionieren
- 📱 Mobile-friendly sein
- 🛡️ Sicherer sein (Validierung)

Viel Erfolg mit deinem neuen Booking-System! 🎊