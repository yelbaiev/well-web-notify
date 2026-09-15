# Check whether a notification was delivered

## What this solves

Someone says they filled in your form and you never saw the message. Before
blaming the form, find out whether the plugin tried to send anything and what
happened when it did.

## Steps

1. In the left-hand menu, open **Well Web Notify → Activity**.
2. Read the **Last 7 days** summary at the top: how many messages were sent,
   how many were delivered, how many failed.
3. If **Failed** is more than zero, click **Show the failures →**.
4. Each row names the channel, the form or order it came from, the result, and
   how long ago. A failed row also carries the reason the chat service gave.

## What success looks like

Every row says **Delivered**, and the times line up with when people submitted
your forms.

## If it doesn't work

**The log is empty.** Nothing has been sent — which usually means no channel is
switched on. Open **Well Web Notify → Settings**; a channel that is ready reads
**Delivering**. One that reads *Set up, switched off* needs its switch turned
on and saved.

**Rows appear for tests but never for real submissions.** On **Settings**, click
**Sources** in the index and check your form plugin is listed as *Watching*. If
it is not, the plugin cannot see it, and no notification will ever be attempted.

**"invalid_token", "channel_not_found" or "404".** The webhook or token was
revoked or the channel was deleted at the other end. Create a new one and paste
it in again.

**Nothing is older than 30 days.** That is deliberate — the log clears itself
so it cannot grow without limit.
