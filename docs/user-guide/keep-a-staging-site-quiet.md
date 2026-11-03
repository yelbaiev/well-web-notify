# Keep a staging site quiet

## What this solves

You have a live site and a staging copy, and Well Web Notify is installed on
both. You want the daily connection check and the weekly summary to run only
from the live site. Forms on staging should still send, so you can test them,
without anyone mistaking a test for a real enquiry.

## Steps

1. On the **live** site, open **Well Web Notify → Settings** and click
   **Monitoring** in the index.
2. Check that the box at the top says **Live site: yourdomain.com**, and that
   **Live domain** shows the same domain. It fills in by itself the first time
   you open the admin on the live site. If it's empty, type the domain (for
   example `client.com`) and click **Save changes**.
3. Open the same screen on the **staging** site. The box should say
   **Staging copy: staging.yourdomain.com** and name the live domain.
4. Send a test form on staging. The message arrives in the chat with
   **[Staging]** at the start of the headline.

## What success looks like

- The live site's Monitoring box says **Live site**. The staging site's box
  says **Staging copy**, and its page header ends with "Staging copy".
- Only the live site runs the daily check and sends the weekly summary.
- Form notifications from staging still arrive, marked **[Staging]**.

## If it doesn't work

**Staging says "Live site".** This happens when staging has its own database
that was never copied from live, and its domain doesn't look like a staging
address. On staging, type the **live** domain into **Live domain** and save.
Alternatively, ask the host to set the WordPress environment type to `staging`.
Many hosts (Pressable, Kinsta, WP Engine) already do this.

**The live site says "Staging copy".** Its address looks like a staging address
(for example it starts with `dev-` or `test-`), so it wasn't recorded
automatically. Type the domain into **Live domain** and save.

**The live site moved to a new domain.** Update **Live domain** on the live
site. Until you do, the checks treat the new domain as staging and stay off.
