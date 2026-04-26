Currently tested with: 

- KoboRoot NickleMenu v0.6.0 (2025-12-06)
- Nextcloud Hub 26 Winter (33.0.2) 
- Calibre2OPDS 0.0.6
- Kobo Clara Color 4.45.23684 (2026-04-10)
- PHP 8.3.30

(These are the version of my apps where it all worked.)

![screenshot](https://github.com/marisabelmunoz/calibre-kobo-bridge/blob/master/Screenshot.png)

# Calibre Kobo Bridge

Sync queued Calibre books from Nextcloud to your Kobo e-reader via NickelMenu

## Background

I built this for my Kobo Clara Colour over 3 days with lots of troubleshooting. It works great on mine, but I only own one Kobo at a time until it dies. Let me know if it works on yours!

Why not just use KOreader? I find it too bloated. It breaks colors on my Clara and makes reading Kobo store books painful (DRM issues, switching back and forth). I just wanted something simple that works with NickelMenu.

Big thanks to the [Calibre2OPDS](https://github.com/oldnomad/calibre_opds) Nextcloud app — this project wouldn't exist without it.


## Requirements

- Nextcloud instance
- [Calibre2OPDS](https://github.com/oldnomad/calibre_opds) Nextcloud app
- Nextcloud app password (not your account password)
- PHP 8.0+ with curl, simplexml, and zip extensions
- Composer
- Kobo e-reader with [NickelMenu](https://pgaskin.net/NickelMenu/) installed

## Installation

### 1. Deploy to your web server

```bash
# Clone the repository
git clone https://github.com/marisabelmunoz/calibre-kobo-bridge.git
cd calibre-kobo-bridge

# Install PHP dependencies
composer install --no-dev --optimize-autoloader
```

> **No Composer?** Download it from [getcomposer.org](https://getcomposer.org/) or run:
> ```bash
> php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
> php composer-setup.php
> php -r "unlink('composer-setup.php');"
> php composer.phar install --no-dev --optimize-autoloader
> ```

### 2. Configure your web server

Point your document root to the project folder (e.g., `/var/www/calibre-kobo-bridge`). No database required.

### 3. Open the app in your browser

Navigate in your server to the URL where this code will run from (e.g., `http://yourserver.com/calibre-kobo-bridge`). The first run will prompt you to enter:

- Nextcloud server URL (with Calibre2OPDS app)
- Nextcloud username and app password
- Your custom access key for API authentication

## Kobo Setup

### 1. Download the sync script

- Go to the **About** page in the web app
- Click **Download kobo_calibre.zip** (script auto-fills with your config)
- Extract and copy `kobo_calibre.sh` to your Kobo

Set permissions (Linux/Mac):
```bash
chmod +x kobo_calibre.sh
```

* you can also do this step after you add it to Kobo if you have SSH access in it.

### 2. Copy script to Kobo

Place the script at:

```
/mnt/onboard/.adds/kobo_calibre.sh
```

### 3. Configure NickelMenu

Create or edit `/mnt/onboard/.adds/nm/config` and add:

```
menu_item :main :Sync Books   :cmd_spawn   :bin/sh /mnt/onboard/.adds/kobo_calibre.sh
menu_item :main :Rescan Books :nickel_misc :rescan_books_full
```

### 4. Reboot your Kobo

## Usage

1. Browse your Calibre library in the web reader
2. Click **⇢ Kobo** on any book to queue it
3. On your Kobo: tap **Sync Books** → **Rescan Books**

## API Endpoints

All calls require `?key=YOUR_ACCESS_KEY`

| Endpoint | Action |
|----------|--------|
| `api.php?action=list` | Get queued books |
| `api.php?action=add_link&url=...&name=...` | Add book to queue |
| `api.php?action=remove&index=N` | Remove by index |
| `api.php?action=clear` | Clear queue |

## Troubleshooting

Check the Kobo sync log:
```bash
cat /mnt/onboard/kobo_sync.log
```

## To Do

- [ ] Better searching function for AND and OR queries.


