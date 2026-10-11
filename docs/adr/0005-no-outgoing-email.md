# ADR-0005: No outgoing e-mail

- **Status:** Accepted (2026-10-11, decided by the owner)
- **Date:** 2026-10-11
- **Author:** architect
- **Supersedes:** the earlier plan decisions "real e-mail on the live demo" (`docs/plan.md` § 9 Q11) and "Gmail SMTP
  as the mail provider" (§ 10 N7). Neither was ever written as an ADR; 0005 was the number reserved for the mail
  provider, so it now records the decision not to have one.

## Context

The plan had the live demo send real e-mail (verification, password reset, booking and invoice mails,
notifications, staff invitations, the contact form) through Gmail SMTP. That brought a lot of cost for a portfolio
demo:
- an external processor (Google, outside the EU) receiving visitor addresses and mail content;
- copies in the Gmail Sent folder that outlive the nightly reset;
- sending limits and account-suspension risk;
- abuse: open registration lets anyone make the app send mail to an arbitrary address;
- a secret (the app password) to protect, plus DNS and deliverability work.

The owner decided that the app sends **no e-mail at all, in any environment**.

## Options

| Option | Pros | Cons |
|---|---|---|
| A. Real mail through a provider (Gmail SMTP, as planned) | Familiar flows (verify, reset by link, notifications) | External processor, retention outside the app, abuse vector, secrets, deliverability work |
| B. Mail only in some environments | Flows can be shown in dev | Two behaviours to build and test; the live demo would still need non-mail alternatives |
| C. **No outgoing e-mail anywhere; in-app alternatives for every flow** | No processor, no abuse vector, no secrets, no deliverability; data really stays in the app | E-mail addresses are unverified; password recovery needs another mechanism; notifications are in-app only |

## Decision

**Option C.** The app has no mail transport and no mail-based features.

| Flow that used e-mail | Replacement | Milestone |
|---|---|---|
| E-mail verification | **Dropped.** The e-mail address is a unique, case-insensitive **login name only**: never verified, never mailed. | M1 |
| Password reset by link | **Account recovery code**: one-time, high-entropy, shown once at registration, stored hashed; e-mail + code sets a new password and issues a fresh code; regenerate from the profile after re-entering the password; rate-limited and audited. Last resort: super_admin artisan command. | M1 |
| Staff invitation mail | **One-time setup code**, shown once to the creator and handed over out of band; entered with the e-mail on `/account/setup`; hashed, single-use, expiring, audited. | M1 |
| Booking confirmation mail | Confirmation page + "My bookings". | M4 |
| Invoice mail | In-app invoice view and PDF download. | M5 |
| Data-export link by mail | In-app download on the profile page (signed, expiring, after re-entering the password). | M6 |
| Message notifications, contact form | **Support-chat widget** with in-app unread badges (polling); guests can chat too. | M8 |

Enforcement:
- Fortify's `emailVerification` and `resetPasswords` features are off; the starter kit's verification/reset pages and
  mail templates are removed.
- A Pest arch test forbids the `Mail` facade, `Mailable` classes, `MailMessage` and the `mail` notification channel.
- The default mailer is `array` in every environment, so even a third-party package can't send anything off the server.
  `.env.example` has no SMTP variables.
- There is no Mailpit container.

## Consequences

- No external processor for mail, no mail secrets, no DNS or deliverability work, and no way to abuse the app to
  send mail to third parties.
- E-mail addresses are unverified. Someone can register an address that isn't theirs. The impact is low because
  nothing is ever sent to it. The privacy statement says the address is only a login name. Super_admin can free up
  an address with an artisan command.
- A user who loses both password and recovery code can't recover the account themselves. That's an accepted
  trade-off (owner decision); super_admin's artisan reset is the documented last resort.
- Recovery and setup codes are secrets equivalent to passwords: hashed, rate-limited, never logged, audited.
- Notifications exist only inside the app. Users see replies only when they come back (unread badges).
- The queue is still used, for PDFs, exports, retention jobs and the demo reset.
- ADR-0001 still holds; Laravel's mail component is simply not used (see the amendment note there).
