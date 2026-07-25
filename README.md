# CH Mail Delivery

Routes all WordPress email through authenticated SMTP (Brevo) with a configurable From and Reply-To. Deployed across the club hockey site portfolio.

## Why

Hostinger shared hosting silently discards unauthenticated PHP `mail()` (shared 100 emails/day account cap). Every site therefore sends through Brevo SMTP from one authenticated sending domain (`connormesec.com` — SPF/DKIM/DMARC verified). `wp_mail()` returning `true` on this host means nothing; only authenticated SMTP actually delivers.

## Settings

**Settings → Mail Delivery** on each site:

- **SMTP host / port / login / key** — shared Brevo credentials (one account for the whole portfolio). The key is stored only in that site's database, never in this repo.
- **From address** — must stay on the authenticated sending domain or mail is dropped again.
- **From name** — per site; blank = the site's name.
- **Reply-To** — per site; where replies go (blank = the site admin email). Emails that set their own Reply-To (e.g. tryout notifications) are not overridden.

SMTP routing is skipped on `.local` (Local dev) hosts so Local's mail catcher keeps working.

## Configure via WP-CLI

```sh
wp option update ch_maildel_settings --format=json \
  '{"smtp_host":"smtp-relay.brevo.com","smtp_port":"587","smtp_login":"…","smtp_key":"…","from_email":"noreply@connormesec.com","from_name":"","reply_to":""}'
```

## Releasing an update

1. Bump the version in `ch-mail-delivery.php` (header + `CH_MAILDEL_VERSION`), commit, push.
2. Create a GitHub release tagged `vX.Y.Z` and tick **"Set as the latest release."**
3. Every live site shows the standard update prompt under Plugins within ~6 hours (immediately with a force update check).
