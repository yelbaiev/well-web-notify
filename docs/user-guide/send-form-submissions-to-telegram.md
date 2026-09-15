# Send form submissions to a Telegram group

## What this solves

Someone fills in a form on your site and you want to know straight away —
without opening email, and with the phone number ready to tap. This puts every
submission into a Telegram group your team is already in.

## Steps

1. In WordPress, open **Well Web Notify → Settings**. The **Channels** section
   is at the top.
2. Click **Telegram** to open it.
3. In Telegram, search for **@BotFather**, send `/newbot`, and answer its two
   questions (a display name, then a username ending in `bot`). BotFather is
   also where you give the bot a photo and description — that is what your team
   will see in the chat.
4. Copy the token BotFather sends you into **Bot token**.
5. Create the Telegram group (or use an existing one) and add your new bot to
   it as a member.
6. Send any message in that group. Then open this address in a browser,
   replacing `YOUR_TOKEN` with the token from step 4:
   `https://api.telegram.org/botYOUR_TOKEN/getUpdates`
   Find `"chat":{"id":-100…}` and copy that number, minus sign included.
7. Paste it into **Chat ID**.
8. Make sure the switch at the top right of the Telegram card is **on**, then
   press **Save changes**.
9. Press **Send test**.

## What success looks like

A message appears in the group within a couple of seconds:

> **Test message**
> yoursite.com
>
> Connected. New form submissions from this site will arrive here.

The button says **Sent**, and the badge on the Telegram card reads
**Delivering**.

From then on, a real submission looks like this:

> **New form submission**
> yoursite.com · Contact us
>
> **Name:** Tetiana
> **Phone:** 0937279861
>
> ☎ `+380937279861`
>
> 💬 WhatsApp   ✈ Telegram   📱 Viber
>
> Open page →

## If it doesn't work

**"Send test" is greyed out.** The token or the chat ID is missing. Fill both
in and press **Save changes** first — the button only wakes up once the channel
has something to send with.

**"chat not found".** The bot is not in the group, or the chat ID is wrong.
Group IDs start with a minus sign; make sure you copied it.

**"Forbidden: bot was blocked by the user".** You are sending to a person, not
a group, and that person has not started a conversation with the bot. Send
`/start` to the bot once from that account.

**The test arrives but real submissions do not.** Scroll to **Sources** and
check your form plugin is listed as *Watching*. If it is, open **Well Web
Notify → Activity** — a failed send is recorded there with the reason the chat
service gave.
