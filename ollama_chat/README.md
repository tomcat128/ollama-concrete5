# Ollama Chat für Concrete CMS

Paket für Concrete CMS 9.5, das einen Block **„Ollama Chat“** bereitstellt. Über ein Eingabefeld stellt man Fragen zur eigenen Concrete-Installation, etwa „Welche Pakete sind installiert?“ oder „Wann wurde die Seite Kontakt zuletzt geändert und von wem?“. Die Antworten kommen von einem lokalen Sprachmodell über [Ollama](https://ollama.com). Grundlage sind die Daten, die das Paket bei jeder Frage aus der Datenbank der Installation liest.

- Für Concrete 9.5 (entwickelt für 9.5.7, getestet mit 9.5.4), PHP 7.3 oder neuer
- Kein festes Gedächtnis: Das Gespräch existiert nur, solange die Seite offen ist
- Nur für angemeldete Benutzer mit Zugang zum Dashboard

## Inhalt

- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Wer darf fragen](#wer-darf-fragen)
- [Was das Modell zu sehen bekommt](#was-das-modell-zu-sehen-bekommt)
- [Gedächtnis](#gedächtnis)
- [Beispielfragen](#beispielfragen)
- [Grenzen](#grenzen)
- [Aufbau](#aufbau)
- [Fehlersuche](#fehlersuche)
- [Deinstallation](#deinstallation)

## Voraussetzungen

| Was | Anforderung |
| --- | --- |
| Concrete CMS | 9.5 oder neuer (entwickelt für 9.5.7, getestet mit 9.5.4) |
| PHP | 7.3 oder neuer, wie von Concrete 9.5 verlangt |
| Ollama | Ein laufender Ollama-Server mit installiertem Modell, z. B. `ollama pull llama3` |
| Netzwerk | **Der Webserver**, auf dem Concrete läuft, muss den Ollama-Server erreichen. Der Browser der Benutzer spricht nie direkt mit Ollama. |

Ob der Webserver Ollama erreicht, lässt sich auf dem Webserver prüfen:

```bash
curl http://localhost:11434/api/version
```

Kommt eine Antwort wie `{"version":"0.40.16"}`, passt es. Läuft Ollama auf einem anderen Rechner oder in einem anderen Container, statt `localhost` dessen Adresse verwenden; siehe [Fehlersuche](#fehlersuche). Ein fertiges Beispiel mit Concrete und Ollama in Docker-Containern liegt im Hauptverzeichnis dieses Repositorys (`docker-compose.yml`, `README.md`).

## Installation

### 1. Paketordner bereitlegen

Benötigt wird der Ordner **`ollama_chat`**, also dieser Ordner mit `controller.php`, `blocks/` und `src/` darin.

- Mit Git: `git clone git@github.com:tomcat128/ollama-concrete5.git`, darin liegt der Ordner `ollama_chat`.
- Als ZIP von GitHub: Das ZIP entpackt sich in einen Ordner wie `ollama-concrete5-main`. Gebraucht wird nur der Unterordner `ollama_chat` daraus, nicht der äußere Ordner.

Der Ordner muss genau `ollama_chat` heißen, klein geschrieben und mit Unterstrich. Concrete erkennt Pakete am Ordnernamen.

### 2. In das Verzeichnis `packages` der Concrete-Installation kopieren

Das Hauptverzeichnis der Concrete-Installation ist das Verzeichnis, in dem `index.php` sowie die Ordner `application`, `concrete` und `packages` liegen. Dorthin, in den Ordner `packages`, kommt `ollama_chat`:

```text
<Concrete-Hauptverzeichnis>/
├── application/
├── concrete/
├── index.php
└── packages/
    └── ollama_chat/          ← hierher
        ├── controller.php
        ├── README.md
        ├── blocks/
        │   └── ollama_chat/
        └── src/
```

Häufige Hauptverzeichnisse sind `/var/www/html`, `/var/www/<domain>/public` oder bei Docker-Images `/srv/app/public`.

Wichtig: `controller.php` muss direkt in `packages/ollama_chat/` liegen. Liegt er eine Ebene tiefer, etwa in `packages/ollama_chat/ollama_chat/controller.php`, findet Concrete das Paket nicht.

Je nach Zugang zum Server:

- **Per SSH**, vom eigenen Rechner aus:

  ```bash
  scp -r ollama_chat benutzer@server:/var/www/html/packages/
  ```

- **Per FTP/SFTP**, etwa mit FileZilla: den Ordner `ollama_chat` in den Ordner `packages` hochladen.
- **In einen Docker-Container**:

  ```bash
  docker cp ollama_chat <container>:/srv/app/public/packages/
  ```

  Dauerhafter ist es, den Ordner als Volume einzubinden, wie im Beispiel `docker-compose.yml`, denn ein mit `docker cp` kopierter Ordner geht verloren, wenn der Container neu erstellt wird.

Der Webserver braucht Leserechte auf die Dateien, Schreibrechte sind nicht nötig. Nach dem Hochladen per SSH gegebenenfalls:

```bash
chmod -R a+rX /var/www/html/packages/ollama_chat
```

### 3. Paket in Concrete installieren

1. Als Administrator anmelden.
2. Im Dashboard **Extend Concrete → Add Functionality** öffnen; in einer deutschen Oberfläche heißen die Menüpunkte entsprechend übersetzt. Direkt erreichbar unter `/index.php/dashboard/extend/install`, bei aktivierten „schönen URLs“ unter `/dashboard/extend/install`.
3. Unter **Awaiting Installation** (noch nicht installiert) erscheint **Ollama Chat**. Auf **Install** klicken und bestätigen.

Erscheint das Paket dort nicht, liegt der Ordner an der falschen Stelle oder hat einen anderen Namen; siehe Schritt 2.

Alternativ auf der Kommandozeile, im Concrete-Hauptverzeichnis und als Benutzer des Webservers:

```bash
sudo -u www-data concrete/bin/concrete c5:package:install ollama_chat
```

### 4. Seite für den Chat anlegen

Der Chat ist ein Block und braucht eine Seite. Am besten eine eigene Seite, die nicht im Menü erscheint:

1. Im Dashboard **Sitemap** öffnen und unter der Startseite eine neue Seite anlegen, etwa „KI-Assistent“.
2. In den Seiteneigenschaften (Attribute) **Exclude From Nav** aktivieren, damit die Seite nicht im Menü auftaucht.

Der Chat zeigt sich ohnehin nur Benutzern mit Dashboard-Zugang (siehe [Wer darf fragen](#wer-darf-fragen)). Die Seite muss also nicht zusätzlich geschützt werden.

### 5. Block einfügen

1. Die Seite aufrufen und oben in der Werkzeugleiste auf den **Stift** klicken (Bearbeitungsmodus).
2. Auf **+** (Hinzufügen) klicken. Den Block **Ollama Chat** findet man in der Kategorie **Other** (in einer deutschen Oberfläche entsprechend übersetzt) oder über die Suche.
3. Den Block in einen Bereich der Seite ziehen, zum Beispiel „Main“.
4. Im Formular die Einstellungen prüfen (siehe unten) und speichern.
5. Oben auf **Publish** bzw. **Veröffentlichen** klicken.

| Einstellung | Standard | Bedeutung |
| --- | --- | --- |
| Überschrift | leer | Wird über dem Chat angezeigt |
| URL des Ollama-Servers | `http://localhost:11434` oder der Wert der Umgebungsvariable `OLLAMA_CHAT_URL` | Vom Webserver aus gesehen. In Docker meist der Name des Ollama-Containers, z. B. `http://ollama:11434` |
| Modell | `llama3:latest` oder der Wert von `OLLAMA_CHAT_MODEL` | Muss auf dem Ollama-Server installiert sein (`ollama list`) |
| Kontextfenster (Tokens) | `8192` | Wie viel Text das Modell auf einmal verarbeitet. `llama3` kann höchstens 8192, `llama3.1` und `llama3.2` bis 131072. Größere Werte erlauben mehr Daten und längere Gespräche, brauchen aber mehr Arbeitsspeicher. |

Die Umgebungsvariablen `OLLAMA_CHAT_URL` und `OLLAMA_CHAT_MODEL` sind nur Vorschläge für neue Blöcke; was im Formular gespeichert ist, gilt.

### 6. Ausprobieren

Die Seite neu laden, eine Frage eingeben, etwa „Welche Pakete sind installiert?“, und auf **Fragen** klicken. Die erste Antwort dauert länger, weil Ollama das Modell lädt; ohne Grafikkarte auch eine Minute oder mehr. Erscheint eine rote Meldung, steht darin die Ursache; siehe [Fehlersuche](#fehlersuche).

### Aktualisieren

1. Den Ordner `packages/ollama_chat` durch die neue Version ersetzen.
2. Hat sich die Version des Pakets geändert, im Dashboard **Extend Concrete → Update Add-Ons** (`/dashboard/extend/update`) auf **Update** klicken, oder:

   ```bash
   sudo -u www-data concrete/bin/concrete c5:package:update ollama_chat
   ```

Bestehende Blöcke und ihre Einstellungen bleiben erhalten.

## Wer darf fragen

Die Antworten verraten Interna der Installation: Benutzernamen, installierte Pakete, Versionen, Einträge im Protokoll. Deshalb gilt:

- **Gäste** sehen den Block gar nicht.
- **Angemeldete Benutzer ohne Zugang zum Dashboard** sehen nur einen Hinweis.
- **Angemeldete Benutzer, die das Dashboard öffnen dürfen**, können fragen. Das sind in der Regel Administratoren und Redakteure.

Der Server prüft die Berechtigung bei jeder Frage erneut und schützt den Endpunkt mit einem CSRF-Token.

## Was das Modell zu sehen bekommt

Bei jeder Frage liest das Paket eine Übersicht aus der Datenbank, ausschließlich lesend:

| Abschnitt | Inhalt |
| --- | --- |
| Allgemein | Name, kanonische URL, Concrete-, PHP- und Datenbankversion, aktives Theme, Wartungsmodus, Cache-Einstellungen, Fehleranzeige |
| Umfang | Anzahl der Seiten, Entwürfe, Seiten im Papierkorb, Benutzer, Dateien, Pakete, Blocktypen |
| Seiten passend zur Frage | Seiten, deren Name oder Pfad ein Wort der Frage enthält, mit Beschreibung, Versionen und den Blöcken darauf |
| Installierte Pakete | Name, Version, Installationsdatum |
| Themes | Alle installierten Themes |
| Seitentypen | Mit Anzahl der Seiten |
| Gruppen | Mit Mitgliedern |
| Benutzer | Name, Gruppen, aktiv, Registrierung, letzte Anmeldung, Anzahl der Anmeldungen |
| Verwendete Blocktypen | Wie oft und auf wie vielen Seiten |
| Seiten | Name, Pfad, Typ, erstellt, zuletzt geändert, von wem |
| Neueste Dateien | Name, Größe, Datum, wer hochgeladen hat |
| Protokoll | Die letzten Warnungen und Fehler |

Nie an das Modell gehen: Passwörter bzw. ihre Hashes, E-Mail-Adressen, IP-Adressen und Zugangsdaten aus der Konfiguration.

Die Daten gehen nur an den eingestellten Ollama-Server. Läuft er im eigenen Netz, verlassen sie es nicht.

## Gedächtnis

Es gibt kein festes Gedächtnis. Die Seite hält das bisherige Gespräch im Speicher des Browsers und schickt es bei jeder Frage mit; Folgefragen wie „Und in welcher Version?“ funktionieren deshalb. Nichts davon wird auf dem Server gespeichert.

Beim Neuladen der Seite, beim Verlassen der Seite und mit **Neues Gespräch** ist das Gespräch weg, und das Modell beginnt mit leerem Gedächtnis.

## Beispielfragen

- Welche Pakete sind installiert, und in welcher Version?
- Welche Seiten wurden zuletzt geändert?
- Wann wurde die Seite „Blog“ zuletzt geändert und von wem?
- Welche Blöcke sind auf der Seite „Kontakt“?
- Welche Benutzer sind Administratoren?
- Wer hat sich zuletzt angemeldet?
- Ist der Seiten-Cache aktiv?
- Gab es zuletzt Fehler im Protokoll?
- Welche Seiten gehören zum Typ „Blog Entry“?

## Grenzen

- **Größe:** Damit alles ins Kontextfenster passt, nutzen die Daten höchstens die Hälfte davon. Lange Listen werden gekürzt: höchstens 150 Seiten (die zuletzt geänderten), 50 Benutzer, 20 Dateien, 10 Protokolleinträge. Das Modell erfährt, dass gekürzt wurde. Fragen zu einer bestimmten Seite funktionieren trotzdem, wenn ihr Name in der Frage vorkommt, weil passende Seiten zusätzlich ausführlich beschrieben werden.
- **Inhalte:** Der Text der Seiten selbst ist nicht enthalten, nur Name, Pfad, Beschreibung, Typ, Daten und die Arten der Blöcke.
- **Modell:** Kleine Modelle wie `llama3` übersehen gelegentlich etwas oder rechnen falsch, besonders bei Fragen, die mehrere Listen verbinden. Größere Modelle antworten zuverlässiger.
- **Geschwindigkeit:** Ohne Grafikkarte dauert eine Antwort einige Sekunden bis Minuten. Die erste Frage ist am langsamsten, weil Ollama das Modell lädt.

## Aufbau

```text
ollama_chat/
├── controller.php                  Paket: Installation des Blocks, Route /ccm/ollama_chat/ask
├── blocks/ollama_chat/
│   ├── controller.php              Block: Einstellungen, Prüfung der Eingaben, Daten für die Ansicht
│   ├── db.xml                      Tabelle btOllamaChat mit den Block-Einstellungen
│   ├── add.php, edit.php, form.php Formular für die Einstellungen
│   ├── view.php                    Chat: Nachrichten, Eingabefeld
│   ├── view.js                     Senden der Fragen, Gedächtnis im Browser, Markdown-Darstellung
│   └── view.css                    Aussehen, passt sich hellen und dunklen Themes an
└── src/                            Namensraum OllamaChat\
    ├── AccessChecker.php           Wer darf fragen
    ├── BlockSettings.php           Einstellungen eines Blocks
    ├── ChatService.php             System-Prompt, Aufteilung des Kontextfensters, Auswahl des Verlaufs
    ├── InstallationContext.php     Liest die Übersicht aus der Datenbank
    ├── OllamaClient.php            Aufruf von Ollama (/api/chat)
    ├── OllamaException.php         Verständliche Fehlermeldungen
    └── Controller/AskController.php Endpunkt für die Fragen
```

Der Endpunkt `POST /ccm/ollama_chat/ask` erwartet JSON mit `token`, `bID`, `question` und optional `history` (Liste von `{ "role": "user" | "assistant", "content": "…" }`). Er antwortet mit `{ "answer": "…", "model": "…" }` oder mit einem Fehlerstatus und `{ "error": "…" }`:

| Status | Bedeutung |
| --- | --- |
| `400` | Frage fehlt oder ist zu lang, oder der Verlauf ist ungültig |
| `403` | Token abgelaufen, oder der Benutzer darf nicht fragen |
| `404` | Der Block existiert nicht mehr |
| `502` | Ollama ist nicht erreichbar, das Modell fehlt, oder Ollama meldet einen Fehler |

## Fehlersuche

- **„Der Ollama-Server unter … ist nicht erreichbar“:** Ollama läuft nicht, oder der Webserver erreicht die eingestellte URL nicht.
  - Läuft Concrete in Docker, ist `localhost` der Container selbst. Dann die Adresse des Rechners mit Ollama eintragen, unter Linux zum Beispiel `http://172.17.0.1:11434`, und Ollama dort auf allen Schnittstellen lauschen lassen (`OLLAMA_HOST=0.0.0.0 ollama serve`).
  - Ollama hat keine Zugangskontrolle. Ist es im Netz erreichbar, kann jeder dort es benutzen; den Port daher per Firewall auf den Webserver beschränken.
- **„Das Modell … ist nicht installiert“:** Mit dem angezeigten `ollama pull`-Befehl auf dem Ollama-Server herunterladen, oder im Block ein installiertes Modell eintragen (`ollama list`).
- **„Die Sitzung ist abgelaufen“:** Die Seite war sehr lange offen, oder man hat sich zwischendurch ab- und angemeldet. Seite neu laden.
- **Die Antwort bricht nach einer Weile mit einem Fehler ab:** Ein Proxy vor dem Webserver hat ein kürzeres Zeitlimit, bei nginx etwa 60 Sekunden (`proxy_read_timeout`). Das Paket selbst wartet bis zu 5 Minuten auf Ollama.
- **Der Block ist nicht zu sehen:** Er ist nur für angemeldete Benutzer sichtbar; siehe [Wer darf fragen](#wer-darf-fragen).

## Deinstallation

Im Dashboard unter **Erweiterungen** bei „Ollama Chat“ auf **Deinstallieren** klicken, oder:

```bash
concrete/bin/concrete c5:package:uninstall ollama_chat
```

Die Blöcke verschwinden mit ihren Einstellungen von den Seiten; nach einer erneuten Installation müssen sie neu eingefügt werden. Die dann leere Tabelle `btOllamaChat` lässt Concrete in der Datenbank stehen. Wer sie nicht mehr braucht, kann sie löschen:

```sql
DROP TABLE btOllamaChat;
```
