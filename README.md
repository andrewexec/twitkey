# Twitkey

Twitkey is a self-hosted PHP 8.2 microblogging app styled after Twitter's 2009 web UI, with modern additions for community notes, verified account badges, business affiliations, direct messages, notifications, and an admin panel.

## Quick Start

```sh
cp .env.example .env
docker compose up -d --build
```

Then open `http://localhost`.

## First Admin

1. Register a normal user at `http://localhost/register`.
2. Promote that user once:

```text
http://localhost/admin/setup?token=changeme123&username=yourusername
```

Set `ADMIN_SETUP_TOKEN` before deployment. After the first successful promotion, Twitkey writes `/data/.admin_setup_done` and the setup route returns 404.

Docker Compose reads `.env` in two ways here: it uses `HTTP_PORT` and `HTTPS_PORT` for host port mapping, and `env_file: .env` passes the application settings into the PHP container.

## Environment Variables

| Variable | Default | Description |
| --- | --- | --- |
| `APP_NAME` | `Twitkey` | Application name in the UI. |
| `APP_URL` | `http://localhost` | Public site URL. |
| `APP_DEBUG` | `false` | Enables PHP error display when `true`. |
| `HTTP_PORT` | `80` | Host port mapped to container port 80. |
| `HTTPS_PORT` | `443` | Host port mapped to container port 443. |
| `DB_DRIVER` | `sqlite` | `sqlite` or `mysql`. |
| `DB_PATH` | `/data/twitkey.db` | SQLite database path. |
| `DB_HOST` | `mysql` | MySQL host. |
| `DB_PORT` | `3306` | MySQL port. |
| `DB_NAME` | `twitkey` | MySQL database. |
| `DB_USER` | `twitkey` | MySQL username. |
| `DB_PASS` | `changeme` | MySQL password. |
| `ADMIN_SETUP_TOKEN` | unset | One-time first-admin setup token. |
| `MAX_AVATAR_SIZE_KB` | `2048` | Maximum uploaded avatar size. |
| `MAX_ATTACHMENT_SIZE_KB` | `5120` | Maximum tweet attachment size. |
| `GIF_API_SEARCH_URL` | `https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrnamespace=6&gsrlimit=12&gsrsearch={query}%20filetype:bitmap%20gif&prop=imageinfo&iiprop=url%7Cmime&format=json&origin=*` | No-key GIF search endpoint. Replace `{query}` with the encoded search term. |
| `LOCATION_SEARCH_URL` | `https://nominatim.openstreetmap.org/search?format=json&limit=6&q={query}` | Location search endpoint. Replace `{query}` with the encoded search term. Respect the provider usage policy or swap in your own endpoint. |

## Features

- 140-character tweets, replies, classic RT retweets, favorites, follows, @replies, search, trends, profile pages, public and home timelines.
- Polls, image attachments, no-key GIF search, map-picked locations, and scheduled posts from the classic compose box.
- Direct messages, notifications, pagination, avatar uploads, and profile settings.
- Community Notes with eligibility, helpful/unhelpful voting, automatic approval/rejection, admin moderation, and misleading-note flags.
- Admin dashboard with user moderation, tweet moderation, note moderation, verification grants, suspensions, account deletion, and audit logging.
- Verified Business and Verified Government badges rendered through the shared badge helper.
- Business affiliation invites, acceptance/decline, revocation, one-business-at-a-time enforcement, and mini-avatar badges wherever names render.
- SQLite first-run bootstrap with schema and index creation. MySQL can be selected with `DB_DRIVER=mysql`.
- CSRF protection, bcrypt passwords, session hardening, prepared PDO queries, server-side tweet length validation, rate limiting, and safe GD avatar resizing.

## Development Checks

```sh
find . -path ./.git -prune -o -name '*.php' -print -exec php -l {} \;
php -S 127.0.0.1:8080 -t public
```

For Docker verification:

```sh
docker compose up -d --build
docker compose logs -f twitkey
```

## Screenshots

Add screenshots here after deploying locally:

- Home timeline
- Profile page with badges
- Tweet detail with Community Note
- Admin dashboard
