# Build e aggiornamento delle immagini

Il repository contiene due fasi distinte:

- `source/Dockerfile` compila Asterisk e prepara i sorgenti FreePBX per una base.
- `maintenance/Dockerfile` aggiunge alla base i pacchetti di sicurezza approvati e gli script di avvio dell'immagine distribuita.

La base di questa revisione è fissata per digest. I pacchetti `.deb` non sono conservati in Git; il loro elenco di versioni è `maintenance/packages/packages.tsv`. Le immagini qui descritte sono Linux AMD64.

## Ricostruire la revisione corrente

Eseguire dalla radice del repository. Il bundle approvato viene recuperato dall'immagine pubblicata, fissata per digest, senza avviare il container:

```bash
BUNDLE_IMAGE='escomputers/freepbx@sha256:094a74c0bc67b6ced2b0f710223260e9e8b7e6e75f6179489f56bf1f4aadc123'

bundle_container=$(docker create "$BUNDLE_IMAGE")
mkdir -p maintenance/packages/debs
rm -f maintenance/packages/debs/*.deb
docker cp "${bundle_container}:/opt/freepbx-security/." maintenance/packages/
docker rm "$bundle_container"

# L'elenco recuperato deve coincidere con quello versionato.
git diff --exit-code -- maintenance/packages/packages.tsv
# Se ci sono differenze, fermarsi e verificarle prima della build.

docker build -f maintenance/Dockerfile \
  -t your-image-name:your-tag maintenance
```

Il risultato usa la base, il bundle e gli script di avvio della revisione corrente. Una nuova build può avere un digest diverso dall'immagine pubblicata per effetto dei metadati di build.

Impostare quindi `services.freepbx.image` nel Compose al nome e tag scelti. Per l'aggiornamento di un'installazione esistente, conservare i volumi e applicare il collaudo descritto al punto 5 prima di distribuire l'immagine.

## Preparare una nuova revisione

I passaggi seguenti servono a scegliere e collaudare un nuovo bundle. Quando viene approvata una nuova revisione, aggiornare insieme il manifest versionato, gli eventuali script modificati e il digest `BUNDLE_IMAGE` sopra, dopo la pubblicazione.

1. **Elencare e scaricare gli aggiornamenti disponibili.** Si avvia un container temporaneo dalla base image della versione corrente (attualmente per freepbx:17-nofail2ban è: escomputers/freepbx-base@sha256:75b3309ff84323aaaeae0da82a5e2ad80c668b2dc7a1fce95bd4703e42a30593), si imposta APT per usare il repository Debian Security e si scaricano i packages in locale:
```bash
############# HOST SIDE #######################################
mkdir -p ~/freepbx-security-packages

# Avvio container temporaneo
docker run --rm -it --entrypoint bash \
  -v "$HOME/freepbx-security-packages:/output" \
  escomputers/freepbx-base@sha256:75b3309ff84323aaaeae0da82a5e2ad80c668b2dc7a1fce95bd4703e42a30593

############# CONTAINER SIDE #######################################

# Impostare Debian Security come sorgente per questi comandi APT
printf '%s\n' \
  'deb http://deb.debian.org/debian-security bookworm-security main' \
  > /tmp/freepbx-security.list

# Aggiornare gli indici dei pacchetti
apt-get \
  -o Dir::Etc::sourcelist=/tmp/freepbx-security.list \
  -o Dir::Etc::sourceparts=- \
  update

# Scaricare gli aggiornamenti
mkdir -p /tmp/security-packages/debs
apt-get \
  -o Dir::Etc::sourcelist=/tmp/freepbx-security.list \
  -o Dir::Etc::sourceparts=- \
  -o Dir::Cache::archives=/tmp/security-packages/debs \
  --download-only --yes upgrade

# Creare l'elenco dei pacchetti scaricati con le rispettive versioni
cd /tmp/security-packages

for pacchetto in debs/*.deb; do
  dpkg-deb --show "$pacchetto"
done > packages.tsv

# Archiviare elenco e pacchetti nella cartella condivisa con l'host
tar -czf /output/freepbx-security-packages.tar.gz \
  packages.tsv debs/*.deb
```

2. **Build dell’immagine aggiornata** Si parte dalla stessa immagine del punto 1 e si installano le versioni specificate nell'archivio creato nello step precedente.

Usare il Dockerfile già versionato in `maintenance/Dockerfile`, che installa il bundle e copia gli script di avvio da questo repository. Non serve creare un Dockerfile separato sul proprio host.

- Estrarre l'archivio nell'area di lavoro:
```bash
mkdir -p ~/freepbx-security-packages/pacchetti
rm -f ~/freepbx-security-packages/pacchetti/debs/*.deb

tar -xzf \
  ~/freepbx-security-packages/freepbx-security-packages.tar.gz \
  -C ~/freepbx-security-packages/pacchetti
```

- Dalla radice del repository, preparare il contesto e costruire la candidata:
```bash
# Rimuovere soltanto i .deb della precedente candidata nel contesto di build.
rm -f maintenance/packages/debs/*.deb
cp -a ~/freepbx-security-packages/pacchetti/. maintenance/packages/

docker build -f maintenance/Dockerfile \
  -t freepbx:17-nofail2ban-securityrc maintenance
```

Il manifest copiato in `maintenance/packages/packages.tsv` fa parte della revisione da esaminare e versionare. La directory `maintenance/packages/debs/` è esclusa da Git.

3. **Verificare il risultato.**
```bash
# Confrontare l’immagine originale con quella nuova. Il risultato deve corrispondere esattamente a packages.tsv (contenuto nell'archivio creato allo step n.1), senza altri pacchetti aggiunti, rimossi o aggiornati.
cd ~/freepbx-security-packages

OLD_IMAGE='escomputers/freepbx-base@sha256:75b3309ff84323aaaeae0da82a5e2ad80c668b2dc7a1fce95bd4703e42a30593'
UPDATED_IMAGE='freepbx:17-nofail2ban-securityrc'

docker run --rm --entrypoint dpkg-query "$OLD_IMAGE" \
  -W '-f=${Package}\t${Version}\t${db:Status-Status}\n' \
  > pacchetti-prima.tsv

docker run --rm --entrypoint dpkg-query "$UPDATED_IMAGE" \
  -W '-f=${Package}\t${Version}\t${db:Status-Status}\n' \
  > pacchetti-dopo.tsv

sort pacchetti-prima.tsv > pacchetti-prima-ordinati.tsv
sort pacchetti-dopo.tsv > pacchetti-dopo-ordinati.tsv

diff -u pacchetti-prima-ordinati.tsv pacchetti-dopo-ordinati.tsv
# Nel risultato controllare che:
# - cambino soltanto i pacchetti elencati in packages.tsv.
# - le nuove versioni coincidano con quelle dell’elenco.
# - non compaiano pacchetti aggiunti o rimossi.
# Quindi le differenze devono coincidere esattamente con gli aggiornamenti previsti, qualunque sia il loro numero.
```

4. **Verificare la compatibilità delle configurazioni.** Per accertarsi che i nuovi aggiornamenti non rompano le configurazioni esistenti, bisogna verificare:
- i file di configurazione gestiti direttamente da `dpkg`, per individuare contenuti cambiati, aggiunti o rimossi rispetto ai vecchi pacchetti, usando le configurazioni originali Debian
- gli script dei pacchetti, per individuare quali configurazioni gestiscono, in quanto possono contenere istruzioni usate durante l’installazione e l’aggiornamento per creare o modificare le configurazioni, anche utilizzando modelli o altri programmi
- i modelli (un file di partenza fornito dal pacchetto) utilizzati che gli script usano per creare o aggiornare le configurazioni; anche uno script rimasto identico può utilizzare un modello aggiornato.
Alla fine di questi controlli, annotare gli eventuali interventi necessari sulle configurazioni e includerli nella procedura di aggiornamento (prossimo step).
```bash
############# HOST SIDE #######################################
docker run --rm -it --entrypoint bash \
  -v "$HOME/freepbx-security-packages:/output" \
  escomputers/freepbx-base@sha256:75b3309ff84323aaaeae0da82a5e2ad80c668b2dc7a1fce95bd4703e42a30593

############# CONTAINER SIDE #################################

####### VERIFICA DEI FILE DI CONFIGURAZIONE
# Estrarre i pacchetti per leggerne il contenuto, senza installarli
mkdir -p /output/verifica-configurazioni

for pacchetto in /output/pacchetti/debs/*.deb; do
  nome=$(dpkg-deb --field "$pacchetto" Package)

  dpkg-deb --raw-extract "$pacchetto" \
    "/output/verifica-configurazioni/$nome"
done

# Confrontare gli elenchi dei file di configurazione e le rispettive impronte
for cartella in /output/verifica-configurazioni/*; do
  [ -d "$cartella/DEBIAN" ] || continue

  nome=${cartella##*/}

  # Configurazioni originali del vecchio pacchetto
  dpkg-query -W -f='${Conffiles}\n' "$nome" \
    | awk 'NF {print $1, $2}' \
    | sort > "$cartella/config-base.txt"

  # Configurazioni contenute nel nuovo pacchetto
  : > "$cartella/config-nuove.txt"

  if [ -s "$cartella/DEBIAN/conffiles" ]; then
    while read -r file resto; do
      [ -n "$file" ] || continue

      impronta=$(md5sum "$cartella$file")
      printf '%s %s\n' "$file" "${impronta%% *}"
    done < "$cartella/DEBIAN/conffiles" \
      | sort > "$cartella/config-nuove.txt"
  fi

  # Confrontare soltanto i pacchetti che dichiarano configurazioni
  if [ -s "$cartella/config-base.txt" ] ||
     [ -s "$cartella/config-nuove.txt" ]; then
    printf '\nPacchetto: %s\n' "$nome"

    diff -u \
      "$cartella/config-base.txt" \
      "$cartella/config-nuove.txt"
  fi
done > /output/verifica-configurazioni/confronto-configurazioni.txt

cat /output/verifica-configurazioni/confronto-configurazioni.txt
# Interpretazione dell'output:
# - soltanto il nome del pacchetto: elenchi e contenuti delle configurazioni sono identici
# - presenza di un `diff`: esaminare i file cambiati, aggiunti o rimossi per stabilire se richiedano un intervento


####### VERIFICA DEGLI SCRIPT DEI PACCHETTI
report_script=/output/verifica-configurazioni/script-installazione.txt
: > "$report_script"

for cartella in /output/verifica-configurazioni/*; do
  [ -d "$cartella/DEBIAN" ] || continue

  nome=${cartella##*/}
  controlli=$(dpkg-query --control-list "$nome")

  for script in preinst postinst prerm postrm config; do
    vecchio="$cartella/${script}-base"

    # Leggere lo script della vecchia versione, se presente
    if printf '%s\n' "$controlli" | grep -Fxq "$script"; then
      dpkg-query --control-show "$nome" "$script" > "$vecchio"
    else
      : > "$vecchio"
    fi

    # Raccogliere il testo del nuovo script, se presente
    nuovo="$cartella/DEBIAN/$script"

    if [ -f "$nuovo" ]; then
      printf '\n=== Pacchetto: %s — script: %s ===\n' \
        "$nome" "$script" >> "$report_script"
      cat "$nuovo" >> "$report_script"
    else
      nuovo=/dev/null
    fi

    # Mostrare soltanto gli script differenti
    if ! cmp -s "$vecchio" "$nuovo"; then
      printf '\nPacchetto: %s — script: %s\n' "$nome" "$script"
      diff -u "$vecchio" "$nuovo"
    fi
  done
done > /output/verifica-configurazioni/confronto-script.txt

cat /output/verifica-configurazioni/confronto-script.txt
# Si producono due file: uno con le differenze rispetto ai vecchi script e uno con il testo completo dei nuovi script, necessario per capire quali configurazioni gestiscono.
# Interpretazione dell'output:
# - nessuna differenza: gli script confrontati sono identici ai precedenti.
# - presenza di un diff: esaminare le istruzioni cambiate e individuare gli eventuali effetti sulle configurazioni.


####### VERIFICA DEI MODELLI

# Esempio per PHP (ripetere per ciascun modello trovato negli script, individuati nel passaggio precedente)
for cartella in /output/verifica-configurazioni/php8.2-*; do
  for nuovo in \
    "$cartella"/usr/lib/php/8.2/php.ini-production* \
    "$cartella"/usr/share/php8.2-*/*/*.ini
  do
    [ -f "$nuovo" ] || continue

    precedente=${nuovo#"$cartella"}

    printf '\nFile: %s\n' "$precedente"
    diff -u "$precedente" "$nuovo"
  done
done > /output/verifica-configurazioni/confronto-modelli-php.txt

cat /output/verifica-configurazioni/confronto-modelli-php.txt
# Interpretazione dell'output:
# - soltanto il nome del file: il modello è identico al precedente.
# - presenza di un diff: esaminare le nuove impostazioni e stabilire se richiedano un intervento sulle configurazioni esistenti.
# - report vuoto: nei pacchetti analizzati non sono stati trovati modelli corrispondenti ai percorsi cercati.
# Per eventuali altri modelli individuati negli script, effettuare lo stesso confronto tra la copia estratta dal nuovo .deb e quella presente nell’immagine base.
```

5. **Provare l’aggiornamento di un’installazione esistente.** Partendo da una installazione esistente e funzionante, che usa l'immagine da aggiornare (es: escomputers/freepbx:17-nofail2ban), conservando i volumi, si sostituisce l’immagine con quella nuova e si verificano configurazione, servizi, restart e ricreazione del container.
```bash
# Dalla radice del repository, con il bundle candidato già preparato.
docker build -f maintenance/Dockerfile \
  -t freepbx:17-nofail2ban-securityrc maintenance

cd ~/freepbx-docker

# In docker-compose.yaml, impostare image: freepbx:17-nofail2ban-securityrc

docker compose up -d --no-deps --force-recreate --pull never freepbx

# Test restart
docker compose restart freepbx

# Test recreation
docker compose up -d --no-deps --force-recreate --pull never freepbx
```

6. **Pubblicare la nuova immagine soltanto dopo queste verifiche.**

La strategia è identica per Docker Hub e GHCR:

- `17-nofail2ban` punta all'ultima revisione approvata.
- I tag numerati `17-nofail2ban-r1`, `r2`, `r3` identificano revisioni immutabili e non vanno sovrascritti. `r1` è già pubblicata.

Ogni revisione avrà una nota di rilascio con le modifiche e il relativo `packages.tsv`. La pubblicazione resta manuale e segue il collaudo; il comando di build non pubblica immagini.

```bash
# Scegliere il prossimo numero libero; r2 è un esempio.
REVISION='17-nofail2ban-r2'

# Docker Hub
docker login docker.io -u escomputers
docker tag freepbx:17-nofail2ban-securityrc "docker.io/escomputers/freepbx:$REVISION"
docker push "docker.io/escomputers/freepbx:$REVISION"

# GHCR
docker login ghcr.io -u escomputers
docker tag "docker.io/escomputers/freepbx:$REVISION" "ghcr.io/escomputers/freepbx:$REVISION"
docker push "ghcr.io/escomputers/freepbx:$REVISION"

# Spostare il tag corrente sulla revisione già pubblicata nello stesso registry,
# senza ricostruire l'immagine.
docker buildx imagetools create \
  --tag docker.io/escomputers/freepbx:17-nofail2ban \
  "docker.io/escomputers/freepbx:$REVISION"

docker buildx imagetools create \
  --tag ghcr.io/escomputers/freepbx:17-nofail2ban \
  "ghcr.io/escomputers/freepbx:$REVISION"

# Controllare che i tag correnti puntino alla revisione pubblicata.
docker buildx imagetools inspect docker.io/escomputers/freepbx:17-nofail2ban
docker buildx imagetools inspect ghcr.io/escomputers/freepbx:17-nofail2ban
```
