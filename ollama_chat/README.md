# Ollama Chat für Concrete CMS

Paket für Concrete CMS 9.5 mit zwei Blöcken, die Fragen über ein Eingabefeld mit Hilfe eines lokalen Sprachmodells über [Ollama](https://ollama.com) beantworten:

| Block | Für wen | Beantwortet |
| --- | --- | --- |
| **Ollama Chat** | Angemeldete Benutzer mit Dashboard-Zugang | Fragen zur Concrete-Installation, etwa „Welche Pakete sind installiert?“ oder „Wann wurde die Seite Kontakt zuletzt geändert und von wem?“. Grundlage sind Daten, die das Paket bei jeder Frage aus der Datenbank liest. |
| **Ferienwohnung: Verfügbarkeit & Preis** | Alle Besucher | Fragen von Gästen wie „Ist vom 3. bis 10. August zu zweit frei, und was kostet das?“. Grundlage sind Belegungsplan und Preisliste des Pakets **Fewo Toolkit**; siehe [Ferienwohnung: Verfügbarkeit & Preis](#ferienwohnung-verfügbarkeit--preis). |

- Für Concrete 9.5 (entwickelt für 9.5.7, getestet mit 9.5.4), PHP 7.3 oder neuer
- Kein festes Gedächtnis: Ein Gespräch existiert nur, solange die Seite offen ist

## Inhalt

- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Wer darf fragen](#wer-darf-fragen)
- [Was das Modell zu sehen bekommt](#was-das-modell-zu-sehen-bekommt)
- [Gedächtnis](#gedächtnis)
- [Beispielfragen](#beispielfragen)
- [Grenzen](#grenzen)
- [Ferienwohnung: Verfügbarkeit & Preis](#ferienwohnung-verfügbarkeit--preis)
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
2. Hat sich die Version des Pakets geändert (zuletzt von 1.0.0 auf 1.1.0 mit dem Block „Ferienwohnung: Verfügbarkeit & Preis“), im Dashboard **Extend Concrete → Update Add-Ons** (`/dashboard/extend/update`) auf **Update** klicken, oder:

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

## Ferienwohnung: Verfügbarkeit & Preis

Ein öffentlicher Block für die Website einer Ferienwohnung. Gäste fragen in eigenen Worten nach freien Terminen und Preisen; die Antworten beruhen auf dem Belegungsplan und der Preisliste des Pakets **Fewo Toolkit**.

### Voraussetzungen

- Das Paket **Fewo Toolkit** (`fewo_toolkit`) ist installiert.
- Auf einer veröffentlichten Seite steht ein Block **„Belegungsplan (Homeaway)“** mit der Adresse des iCal-Feeds.
- Es gibt eine Preisliste im Dashboard des Fewo Toolkits; am besten steht auch ein Block **„Preisrechner“** auf einer Seite.

Pro Concrete-Installation gibt es eine Ferienwohnung. Der Block findet Belegungsplan und Preisliste deshalb selbst; im Bearbeitungsformular zeigt er an, was er gefunden hat:

| Daten | Woher |
| --- | --- |
| Belegung | iCal-Feed aus dem Block „Belegungsplan (Homeaway)“ |
| Preisliste und Berechnungsmodus | Aus dem Block „Preisrechner“ (Preisliste und ob die Saison des Anreise- oder des Folgetags zählt). Ohne Preisrechner: die Preisliste eines Blocks „Preisübersicht“ oder die einzige vorhandene Preisliste, mit dem Modus „Folgetag“. |

### Einrichten

1. Paket aktualisieren bzw. installieren, wie unter [Installation](#installation) beschrieben.
2. Eine Seite anlegen, etwa „Anfrage“, oder eine bestehende nehmen, etwa die Seite mit dem Belegungsplan.
3. Im Bearbeitungsmodus den Block **Ferienwohnung: Verfügbarkeit & Preis** (Kategorie **Other**) einfügen und die Einstellungen prüfen.
4. Veröffentlichen.

| Einstellung | Standard | Bedeutung |
| --- | --- | --- |
| Überschrift | leer | Wird über dem Chat angezeigt |
| Seite für Anfragen | keine | Unter dem Chat erscheint ein Link „Jetzt anfragen“ dorthin, etwa zum Kontaktformular |
| Höchstalter des Belegungsplans | 24 Stunden | Konnte der Feed länger nicht aktualisiert werden, nennt der Assistent keine freien Termine, sondern bittet um eine direkte Anfrage |
| URL des Ollama-Servers, Modell, Kontextfenster | wie beim Block „Ollama Chat“ | Siehe [Block einfügen](#5-block-einfügen) |

### So entsteht eine Antwort

1. **Das Modell versteht die Frage**: Es bestimmt, ob der Gast nach einem bestimmten Aufenthalt, nach freien Zeiträumen oder allgemein nach Preisen fragt, und liest Anreise, Abreise, Anzahl der Gäste und die Sprache heraus. Mehr macht das Modell nicht.
2. **Verfügbarkeit**: Das Paket prüft den Zeitraum im iCal-Feed. Es liest den Feed wie der Block „Belegungsplan (Homeaway)“: Jeder Termin ist eine Belegung vom Anreisetag bis zum Abreisetag; der Abreisetag ist ab nachmittags frei, eine neue Anreise an diesem Tag ist möglich. Termine mit dem Titel „Auf Anfrage“ gelten als „nur auf Anfrage“. Der Feed wird in derselben Datei zwischengespeichert, die auch der Belegungsplan-Block nutzt, und nach dessen Aktualisierungsintervall neu geladen.
3. **Preis**: Den Preis berechnet die Methode `Preisuebersicht::calculate()` des Fewo Toolkits, dieselbe, die der Preisrechner auf der Website verwendet. Der Chat nennt also immer denselben Preis wie der Preisrechner.
4. **Antwort**: Das Paket setzt die Antwort aus festen Texten auf Deutsch oder Englisch zusammen, mit den berechneten Daten und Beträgen.

Warum formuliert das Modell die Antwort nicht selbst? In Tests hat `llama3` sich dabei nicht an die berechneten Werte gehalten: Es rechnete Preise selbst nach und lag falsch, nannte belegte Zeiträume „frei“ und übersah den Mindestaufenthalt. Bei Terminen und Preisen für Gäste muss jede Angabe stimmen, deshalb hat das Modell hier nur die Aufgabe, die Frage zu verstehen. Die Antwort nennt die verstandenen Daten ausdrücklich, sodass Gäste ein Missverständnis sofort bemerken.

### Was Gäste fragen können

| Frage | Antwort |
| --- | --- |
| „Ist vom 3. bis 10. August zu zweit frei, und was kostet das?“ | Frei, belegt oder nur auf Anfrage; bei belegt freie Zeiträume in der Nähe; der Gesamtpreis mit Aufschlüsselung nach Saisons und Endreinigung, dazu die Anzahlung |
| „Und für 4 Personen?“, „Und vom 19. bis 26. August?“ | Folgefragen übernehmen die übrigen Angaben aus der vorigen Frage |
| „Wann ist im Januar noch frei?“ | Die freien Zeiträume im genannten Zeitraum; ohne Angabe in den nächsten drei Monaten |
| „Was kostet bei euch eine Nacht?“ | Die Saisonpreise, Endreinigung, Mindestaufenthalt und höchste Gästezahl |
| Fragen auf Englisch | Antworten auf Englisch |
| Andere Themen, etwa Ausstattung | Ein Hinweis, dass der Assistent nur zu Terminen und Preisen Auskunft gibt |

Ein Datum ohne Jahr bezieht sich auf das nächste Mal, an dem es kommt; „im November“ während des Novembers meint den laufenden. Zu kurze Aufenthalte, zu viele Gäste und Zeiträume ohne hinterlegte Preise werden mit dem Grund beantwortet, statt einen Preis zu nennen. Alle Antworten mit Verfügbarkeit oder Preis enden mit dem Hinweis, dass die Angaben unverbindlich sind.

### Schutz und Datenschutz

- **Keine Gästedaten**: Der Block nennt nur, was Belegungsplan und Preisliste auf der Website ohnehin zeigen. Titel und andere Inhalte der Kalendertermine werden nicht ausgegeben, nur die Zeiträume.
- **Nichts wird gespeichert**: Das Gespräch und die zuletzt verstandene Anfrage hält nur die Seite im Browser und schickt beides mit jeder Frage mit.
- **Begrenzung der Anfragen**: Jeder Besucher kann 20 Fragen in 10 Minuten stellen, danach erhält er einen Hinweis. Dafür speichert das Paket in der Tabelle `OllamaChatRateLimits` einen Hashwert seiner IP-Adresse mit einem geheimen, zufälligen Salz, nie die Adresse selbst; die Einträge werden nach Ablauf der 10 Minuten gelöscht.
- **Kein CSRF-Token**: Der Endpunkt ändert nichts und gibt nur öffentliche Informationen heraus; ein Token würde auf zwischengespeicherten Seiten veralten.

### Endpunkt

`POST /ccm/ollama_chat/fewo` erwartet JSON mit `bID`, `question`, optional `history` (wie beim Endpunkt des Admin-Chats) und optional `context` (die mit der vorigen Antwort gelieferte Anfrage). Er antwortet mit `{ "answer": "…", "model": "…", "context": { "arrival": "JJJJ-MM-TT", "departure": "JJJJ-MM-TT", "guests": 2 } }` oder mit einem Fehlerstatus und `{ "error": "…" }`:

| Status | Bedeutung |
| --- | --- |
| `400` | Frage fehlt oder ist zu lang, oder der Verlauf ist ungültig |
| `404` | Der Block existiert nicht mehr |
| `429` | Zu viele Fragen in kurzer Zeit |
| `502` | Ollama ist nicht erreichbar oder meldet einen Fehler (Einzelheiten im Concrete-Protokoll) |
| `503` | Kein Belegungsplan und keine Preisliste gefunden |

### Hinweis zum Fewo Toolkit

Der Job „Belegungsplan (Homeaway)“ des Fewo Toolkits aktualisiert nur Blöcke mit `Blocks.bIsActive = 1`. Concrete 9 setzt diese Spalte bei neu angelegten Blöcken nicht mehr; für solche Blöcke aktualisiert der Job den Feed deshalb nicht. Der Block „Ferienwohnung: Verfügbarkeit & Preis“ ist davon nicht abhängig: Er lädt den Feed selbst neu, wenn die zwischengespeicherte Datei älter ist als das Aktualisierungsintervall des Belegungsplans.

## Aufbau

```text
ollama_chat/
├── controller.php                    Paket: Installation der Blöcke, Routen, gemeinsame Assets
├── db.xml                            Tabelle OllamaChatRateLimits (Begrenzung der öffentlichen Anfragen)
├── js/ollama-chat.js                 Chat im Browser für beide Blöcke: Senden, Gedächtnis, Markdown
├── css/ollama-chat.css               Aussehen, passt sich hellen und dunklen Themes an
├── blocks/ollama_chat/               Block „Ollama Chat“ (Fragen zur Installation)
│   ├── controller.php                Einstellungen, Prüfung der Eingaben, Daten für die Ansicht
│   ├── db.xml                        Tabelle btOllamaChat
│   ├── add.php, edit.php, form.php   Formular für die Einstellungen
│   └── view.php                      Chat
├── blocks/ollama_fewo/               Block „Ferienwohnung: Verfügbarkeit & Preis“
│   ├── controller.php, db.xml        Einstellungen, Tabelle btOllamaFewo
│   ├── add.php, edit.php, form.php   Formular, zeigt die gefundenen Daten des Fewo Toolkits
│   └── view.php                      Chat für Gäste
└── src/                              Namensraum OllamaChat\
    ├── AccessChecker.php             Wer den Admin-Chat nutzen darf
    ├── BlockSettings.php             Verbindung zu Ollama (URL, Modell, Kontextfenster)
    ├── ChatRequest.php               Prüfung von Frage und Verlauf
    ├── ChatService.php               Admin-Chat: System-Prompt, Aufteilung des Kontextfensters
    ├── ContextWindow.php             Schätzung der Tokens, Auswahl des Verlaufs
    ├── InstallationContext.php       Admin-Chat: liest die Übersicht aus der Datenbank
    ├── OllamaClient.php              Aufruf von Ollama (/api/chat), auch mit JSON-Schema
    ├── OllamaException.php           Verständliche Fehlermeldungen
    ├── RateLimiter.php               Begrenzung der öffentlichen Anfragen
    ├── Controller/AskController.php  Endpunkt des Admin-Chats
    ├── Controller/FewoAskController.php Endpunkt des Ferienwohnungs-Chats
    └── Fewo/
        ├── FewoSources.php           Findet Belegungsplan und Preisliste des Fewo Toolkits
        ├── OccupancyCalendar.php     Liest den iCal-Feed, prüft Zeiträume, findet freie Zeiträume
        ├── PriceCalculator.php       Prüft die Eingaben und ruft die Preisberechnung des Fewo Toolkits auf
        ├── RequestExtractor.php      Lässt das Modell die Frage verstehen (JSON-Schema)
        ├── StayRequest.php           Die verstandene Anfrage
        ├── FewoAssistant.php         Setzt die Antwort zusammen
        ├── FewoTexts.php             Textbausteine auf Deutsch und Englisch
        ├── FewoBlockSettings.php     Einstellungen des Blocks
        └── FewoUnavailableException.php
```

Der Endpunkt des Admin-Chats, `POST /ccm/ollama_chat/ask`, erwartet JSON mit `token`, `bID`, `question` und optional `history` (Liste von `{ "role": "user" | "assistant", "content": "…" }`). Er antwortet mit `{ "answer": "…", "model": "…" }` oder mit einem Fehlerstatus und `{ "error": "…" }`:

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

Die Blöcke verschwinden mit ihren Einstellungen von den Seiten; nach einer erneuten Installation müssen sie neu eingefügt werden. Die Tabellen lässt Concrete in der Datenbank stehen. Wer sie nicht mehr braucht, kann sie löschen:

```sql
DROP TABLE btOllamaChat, btOllamaFewo, OllamaChatRateLimits;
```
