# Know notifications still work without messaging the chat

## What this solves

The people in the chat (often the client) don't want a "health check" message
every morning. You still want to hear quickly if the bot is removed from the
group or a webhook stops working.

## Steps

1. Open **Well Web Notify → Settings** and click **Monitoring** in the index.
2. In **Alert email**, enter the address that should hear about problems, for
   example your agency's support inbox. If you leave it empty, alerts go to the
   site admin email.
3. Optional: switch on **Weekly summary in the chat** if the chat should get one
   short message each Monday saying how many notifications arrived that week.
4. Click **Save changes**.

## What success looks like

- Nothing is posted to the chat by the daily check.
- After the first daily check, Monitoring lists each channel as **Connected**,
  with the time of the last check below.
- If the bot is removed from the group or a webhook is deleted, the alert email
  receives "[Well Web Notify] Connection problem on yoursite.com" naming the
  channel and the reason. The email repeats daily until the problem is fixed.
- With the weekly summary on, each chat gets "Weekly summary" on Monday morning.
  In Telegram and Discord it arrives without a notification sound.

## If it doesn't work

**The channel list under Monitoring is empty.** The check hasn't run yet. It
runs once a day, early in the morning (UTC), on the next visit to the site
after that time.

**No alert email arrived, but a channel shows an error.** The site may not be
able to send email. Check that other WordPress emails (for example password
resets) arrive, or install an SMTP plugin.

**No checks or summaries at all.** Check the box at the top of Monitoring. If
it says **Staging copy**, this site isn't treated as the live one. See
[Keep a staging site quiet](keep-a-staging-site-quiet.md).
