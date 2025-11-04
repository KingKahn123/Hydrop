#HYDROP für IP-Symcon

Ein IP-Symcon-Modul zur Integration von **HYDROP-Wasserzählern** über die offizielle REST-API  
👉 [https://api.hydrop-systems.com](https://api.hydrop-systems.com)

Das Modul liest aktuelle Messdaten (Gesamtverbrauch, Durchfluss, Zeitstempel, Gerät) aus und stellt sie als Variablen in IP-Symcon bereit.
---

## 🧩 Funktionen

- Automatische Abfrage der HYDROP-REST-API in festen Intervallen  
- Messwerte:
  - **Gesamtverbrauch (m³)**
  - **Durchfluss (L/min)** – berechnet aus Zählerdifferenz über die Zeit  
  - **Zeitstempel (Unixzeit)**
  - **Gerät (ID / Name)**
- Optionale automatische Variablen-Erstellung (Auto-Mapper)  
- Konfigurierbarer API-Zugriff (URL, Header, Key, Endpoint, Intervall)  
- Unterstützt mehrere Zähler durch separate Instanzen  
- Manuelle Testabfrage und Timersteuerung direkt im Formular  
- Korrekte Einheiten dank eigener Variablenprofile (`m³`, `L/min`)

---

## ⚙️ Installation

1. Dieses Modul-Repository in IP-Symcon hinzufügen:
   ```
   https://<dein-github-repo>
   ```
2. Modul aktualisieren, falls es bereits eingebunden war.
3. Neue Instanz anlegen:  
   - Objekt hinzufügen → **Instanz** → **Hydrop**

---

## 🔧 Konfiguration

| Feld | Beschreibung |
|------|---------------|
| **Base URL** | Standard: `https://api.hydrop-systems.com` |
| **Auth Header Name** | `apikey` *(laut HYDROP-API)* |
| **Auth Header Prefix** | leer lassen |
| **API Key** | Dein persönlicher API-Key |
| **Endpoint Path** | z. B. `/sensors/all/newest` |
| **Meter ID (optional)** | Wird automatisch ersetzt, falls im Pfad `{meterId}` vorkommt |
| **Poll-Intervall (Sekunden)** | Zeit zwischen automatischen Abfragen (≥ 10 s) |
| **Alle JSON-Felder automatisch anlegen** | (Checkbox) legt zusätzlich alle Felder der JSON-Antwort als Variablen an |

---

## ▶️ Bedienung

**Buttons im Formular:**
- **Testen (einmal abfragen)** → ruft den Endpoint sofort ab  
- Der Poll-Timer startet nach „Übernehmen“ automatisch.

---

## 💧 Berechnete Werte

| Variable | Einheit | Beschreibung |
|-----------|----------|--------------|
| `Gesamtverbrauch (m³)` | m³ | Aktueller Gesamtzählerstand |
| `Zeitstempel` | Unix-Zeit | Zeitpunkt der Messung |
| `Gerät` | – | ID/Name aus der API |
| `Durchfluss (L/min)` | L/min | Berechnet aus ΔZählerstand / ΔZeit |

Berechnungsformel:
```
Δm³ = aktueller_meterValue - letzter_meterValue
Δt = aktueller_timestamp - letzter_timestamp
Durchfluss [L/min] = (Δm³ * 1000) * (60 / Δt)
```

Der Durchflusswert erscheint **ab dem zweiten erfolgreichen Poll**.

---

## 🧠 Hinweise

- HYDROP-API nutzt Header `apikey: <KEY>` (kein Bearer-Token).  
- Wenn du andere Endpunkte abrufst (z. B. `/devices` oder `/measurements`), kannst du die JSON-Felder mit dem Auto-Mapper erkunden.  
- Der Auto-Mapper kann im Formular deaktiviert werden, damit nur die Standard-Variablen angelegt bleiben.

---

## 📄 Modulstruktur

```
library.json             // Bibliothekseintrag
HydropModule/
├── module.json          // Instanzdefinition (type=3)
├── module.php           // Logik & API-Aufrufe
└── form.json            // Instanzformular
```

---

## 🧑‍💻 Autor & Lizenz

| Feld | Info |
|------|------|
| **Autor:** | Kai Stockmann |
| **Version:** | 1.1 |
| **Kompatibel mit:** | IP-Symcon ≥ 7.0 |
| **Lizenz:** | MIT |
| **Repository:** | https://github.com/KingKahn123/Hydrop.git |

