# Ollama für Concrete CMS

Dieses Repository enthält:

- **[`ollama_chat/`](ollama_chat/)**: ein Paket für Concrete CMS 9.5 mit dem Block „Ollama Chat“. Über ein Eingabefeld stellt man Fragen zur eigenen Concrete-Installation; ein lokales Sprachmodell beantwortet sie anhand der Daten aus der Datenbank. Installation in eine bestehende Concrete-Installation und alle Details: [ollama_chat/README.md](ollama_chat/README.md).
- **`docker-compose.yml`**: ein Beispiel, wie Concrete, die Datenbank und Ollama gemeinsam in Docker-Containern laufen, mit dem Paket bereits eingerichtet. Darum geht es in dieser Datei.

## Inhalt

- [Aufbau](#aufbau)
- [Voraussetzungen](#voraussetzungen)
- [Schnellstart](#schnellstart)
- [Was beim ersten Start passiert](#was-beim-ersten-start-passiert)
- [Konfiguration](#konfiguration)
- [Das Concrete-Image](#das-concrete-image)
- [Bestehende Concrete-Container um Ollama ergänzen](#bestehende-concrete-container-um-ollama-ergänzen)
- [Grafikkarte](#grafikkarte)
- [Betrieb](#betrieb)
- [Sicherheit](#sicherheit)
- [Fehlersuche](#fehlersuche)

## Aufbau

```text
                 Host (Port 8080)
                        │
             ┌──────────▼──────────┐        ┌─────────────────────┐
  Browser ──▶│ concrete            │──SQL──▶│ db (MariaDB 11.4)   │
             │ Apache + PHP        │        │ Volume: db-data     │
             │ Paket ollama_chat   │        └─────────────────────┘
             │ Volume: concrete-   │
             │   application       │        ┌─────────────────────┐
             └─────────────────────┘──HTTP─▶│ ollama              │
                                            │ http://ollama:11434 │
                                            │ Volume: ollama-data │
                                            └─────────────────────┘
```

| Dienst | Aufgabe |
| --- | --- |
| `concrete` | Webserver mit Concrete CMS; einziger Dienst mit einem Port nach außen |
| `db` | MariaDB-Datenbank von Concrete |
| `ollama` | Sprachmodell-Server; nur im internen Docker-Netz erreichbar |
| `ollama-pull` | Läuft kurz bei jedem Start und lädt das Modell, falls es fehlt |
| `concrete-setup` | Läuft kurz bei jedem Start: installiert beim ersten Mal Concrete, das Paket und eine Beispielseite |

Die Container finden sich gegenseitig über ihre Dienstnamen. Concrete erreicht Ollama deshalb unter `http://ollama:11434` und die Datenbank unter `db`.

## Voraussetzungen

| Was | Anforderung |
| --- | --- |
| Docker | Docker Engine mit Compose v2 (`docker compose version`) |
| Arbeitsspeicher | Mindestens 8 GB frei für `llama3`; das Modell allein belegt rund 5 bis 6 GB |
| Speicherplatz | Rund 10 GB: Ollama-Image 3,4 GB, Modell `llama3` 4,7 GB, Concrete und Datenbank |
| Internet | Beim ersten Start, zum Laden der Images und des Modells |

## Schnellstart

```bash
git clone git@github.com:tomcat128/ollama-concrete5.git
cd ollama-concrete5

cp .env.example .env
# In .env mindestens DB_PASSWORD, DB_ROOT_PASSWORD und C5_ADMIN_PASSWORD setzen

docker compose up -d
```

Beim ersten Start dauert `docker compose up -d` etwa eine Minute, weil Concrete installiert wird. Das Modell lädt parallel und braucht je nach Verbindung mehrere Minuten. Den Fortschritt zeigt:

```bash
docker compose logs -f ollama-pull
```

Danach:

1. **http://localhost:8080/index.php/ki-assistent** öffnen.
2. Über den Link „Log in“ unten auf der Seite oder unter http://localhost:8080/index.php/login mit dem Benutzer `admin` und dem Passwort aus `C5_ADMIN_PASSWORD` anmelden.
3. Zur Seite „KI-Assistent“ zurückkehren und eine Frage stellen, etwa „Welche Pakete sind installiert?“.

Die erste Antwort dauert ohne Grafikkarte eine Minute oder länger, weil Ollama das Modell erst in den Arbeitsspeicher lädt. Danach geht es schneller.

Die Seite „KI-Assistent“ erscheint nicht im Menü und der Chat nur für angemeldete Benutzer mit Dashboard-Zugang.

## Was beim ersten Start passiert

1. `db` und `ollama` starten.
2. `ollama-pull` wartet, bis Ollama bereit ist, und lädt das Modell aus `OLLAMA_MODEL` in das Volume `ollama-data`.
3. `concrete-setup` wartet auf die Datenbank und führt [`docker/concrete-setup.sh`](docker/concrete-setup.sh) aus:
   - installiert Concrete mit dem Startpunkt aus `C5_STARTING_POINT` und dem Benutzer `admin`,
   - installiert das Paket `ollama_chat`,
   - legt die Seite `/ki-assistent` mit einem Chat-Block an, der `http://ollama:11434` und das Modell aus `OLLAMA_MODEL` verwendet.
4. `concrete` startet, sobald `concrete-setup` fertig ist.

Bei jedem weiteren Start prüft `concrete-setup` nur, ob alles vorhanden ist, und ändert nichts. Löscht man die Beispielseite, legt es sie beim nächsten Start wieder an.

## Konfiguration

Alle Einstellungen stehen in `.env`; die Vorlage ist [`.env.example`](.env.example).

| Variable | Standard | Bedeutung |
| --- | --- | --- |
| `DB_PASSWORD` | – (Pflicht) | Passwort des Datenbankbenutzers |
| `DB_ROOT_PASSWORD` | – (Pflicht) | Passwort des Datenbank-Administrators |
| `DB_DATABASE`, `DB_USER` | `concrete` | Name der Datenbank und des Benutzers |
| `C5_ADMIN_PASSWORD` | – (Pflicht) | Passwort des Concrete-Benutzers `admin`; nur bei der Erstinstallation verwendet |
| `C5_ADMIN_EMAIL` | `admin@example.com` | E-Mail-Adresse des Benutzers `admin` |
| `C5_IMAGE` | `fehrenbacher/concrete5:9.5.4_php8.4.25` | Image mit Concrete, siehe [Das Concrete-Image](#das-concrete-image) |
| `C5_BIND` | `127.0.0.1` | Auf welcher Adresse des Hosts Concrete erreichbar ist: `127.0.0.1` nur von diesem Rechner, `0.0.0.0` aus dem ganzen Netz |
| `C5_PORT` | `8080` | Port auf dem Host |
| `C5_CANONICAL_URL` | `http://localhost:8080/` | Adresse, unter der die Seite im Browser aufgerufen wird; nur bei der Erstinstallation verwendet |
| `C5_SITE_NAME` | `Concrete mit Ollama` | Name der Website; nur bei der Erstinstallation verwendet |
| `C5_STARTING_POINT` | `atomik_full` | Inhalt der Erstinstallation: `atomik_full` (mit Demo-Inhalten), `atomik_blank`, `elemental_full`, `elemental_blank` |
| `OLLAMA_VERSION` | `0.40.16` | Version des Ollama-Images |
| `OLLAMA_MODEL` | `llama3:latest` | Modell; wird geladen und neuen Chat-Blöcken vorgeschlagen |
| `OLLAMA_KEEP_ALIVE` | `30m` | Wie lange das Modell nach der letzten Frage im Speicher bleibt |

Ohne die Pflichtwerte verweigert `docker compose` den Start mit einer Meldung wie `required variable DB_PASSWORD is missing a value`.

Ein anderes Modell, etwa `llama3.1` mit größerem Kontextfenster: `OLLAMA_MODEL` ändern, `docker compose up -d` ausführen (lädt das Modell), und im Chat-Block das Modell und gegebenenfalls das Kontextfenster anpassen. Bestehende Blöcke übernehmen die Änderung nicht automatisch.

## Das Concrete-Image

Das Beispiel verwendet das Image `fehrenbacher/concrete5:9.5.4_php8.4.25`. Ein anderes Image, etwa mit Concrete 9.5.7, lässt sich über `C5_IMAGE` einsetzen, wenn es diese Annahmen erfüllt:

- Concrete liegt entpackt, aber noch nicht installiert, unter `/srv/app/public`.
- Apache lauscht im Container auf Port 80 und läuft als `www-data`.
- PHP enthält mindestens `pdo_mysql`, `gd`, `zip`, `mbstring`, `intl` bzw. was Concrete verlangt.

Liegt Concrete woanders, müssen die Pfade in `docker-compose.yml` (Volumes) und in `docker/concrete-setup.sh` angepasst werden.

Die Installation selbst, also Konfiguration und hochgeladene Dateien, liegt im Volume `concrete-application` (`/srv/app/public/application`). Der Concrete-Kern kommt aus dem Image. Bei einem neueren Image bleibt die Installation deshalb erhalten; die Datenbank muss danach aktualisiert werden:

```bash
docker compose exec -u www-data concrete concrete/bin/concrete c5:update
```

Das Image aktiviert keine „schönen URLs“, deshalb enthalten die Adressen `index.php`. Concrete schreibt die dafür nötige `.htaccess` in das Hauptverzeichnis, das im Image liegt und beim Neuerstellen des Containers verloren ginge. Wer schöne URLs möchte, sollte die `.htaccess` ins Image aufnehmen oder als Datei einbinden.

## Bestehende Concrete-Container um Ollama ergänzen

Läuft Concrete bereits mit Docker Compose, genügen drei Schritte:

1. Den Dienst `ollama` (und bei Bedarf `ollama-pull`) aus `docker-compose.yml` in die eigene Compose-Datei übernehmen, samt Volume `ollama-data`. Beide müssen im selben Compose-Projekt oder im selben Docker-Netz liegen wie Concrete.
2. Das Paket installieren, wie in [ollama_chat/README.md](ollama_chat/README.md#installation) beschrieben; den Ordner am besten als Volume nach `/srv/app/public/packages/ollama_chat` einbinden.
3. Im Chat-Block als URL `http://ollama:11434` eintragen. Optional dem Concrete-Dienst `OLLAMA_CHAT_URL: http://ollama:11434` als Umgebungsvariable geben, dann schlagen neue Blöcke diese URL vor.

## Grafikkarte

Ohne Grafikkarte rechnet Ollama auf der CPU; eine Antwort dauert dann einige Sekunden bis Minuten. Mit einer NVIDIA-Grafikkarte:

1. Auf dem Host das [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/latest/install-guide.html) installieren.
2. In `docker-compose.yml` beim Dienst `ollama` den Abschnitt `deploy:` einkommentieren.
3. `docker compose up -d`.

## Betrieb

| Aufgabe | Befehl |
| --- | --- |
| Starten | `docker compose up -d` |
| Stoppen | `docker compose stop` |
| Status | `docker compose ps -a` |
| Protokolle | `docker compose logs -f concrete` (oder `ollama`, `db`, `concrete-setup`) |
| Concrete-Befehle | `docker compose exec -u www-data concrete concrete/bin/concrete <befehl>` |
| Installierte Modelle | `docker compose exec ollama ollama list` |
| Container entfernen, Daten behalten | `docker compose down` |
| **Alles löschen**, auch Datenbank, Modelle und Uploads | `docker compose down -v` |

Die Daten liegen in drei Volumes: `db-data` (Datenbank), `concrete-application` (Konfiguration und Uploads) und `ollama-data` (Modelle). Für eine Sicherung genügen die ersten beiden plus ein Datenbank-Dump:

```bash
docker compose exec db sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > concrete.sql
```

## Sicherheit

- **Ollama** hat keine Zugangskontrolle. Im Beispiel ist es deshalb nur im internen Docker-Netz erreichbar und nicht vom Host oder aus dem Netz.
- **Concrete** ist standardmäßig nur von diesem Rechner aus erreichbar (`C5_BIND=127.0.0.1`). Für einen Betrieb im Netz oder im Internet gehört ein Reverse-Proxy mit HTTPS davor.
- **`.env`** enthält Passwörter und steht in `.gitignore`. Nicht einchecken.
- Der Chat verrät Interna der Installation und ist deshalb nur für Benutzer mit Dashboard-Zugang freigegeben; siehe [ollama_chat/README.md](ollama_chat/README.md#wer-darf-fragen).

## Fehlersuche

- **`concrete` startet nicht, `concrete-setup` ist mit einem Fehler beendet:** `docker compose logs concrete-setup` zeigt die Ursache. Häufig ist die Datenbank nach einem geänderten Passwort nicht mehr erreichbar; die Passwörter gelten nur bei der allerersten Einrichtung eines Volumes.
- **Port 8080 ist belegt:** In `.env` einen anderen `C5_PORT` wählen und `C5_CANONICAL_URL` passend setzen (vor der Erstinstallation).
- **Der Chat meldet „Das Modell … ist nicht installiert“:** Das Laden läuft noch oder ist fehlgeschlagen: `docker compose logs ollama-pull`. Erneut versuchen mit `docker compose up -d`.
- **Der Chat meldet „Der Ollama-Server … ist nicht erreichbar“:** `docker compose ps` zeigen lassen, ob `ollama` läuft. Im Block muss `http://ollama:11434` stehen, nicht `localhost`, denn `localhost` ist im Concrete-Container der Container selbst.
- **Antworten brechen ab oder sind sehr langsam:** Zu wenig Arbeitsspeicher für das Modell; `docker compose logs ollama` zeigt das. Ein kleineres Modell wie `llama3.2` wählen oder eine Grafikkarte nutzen.
