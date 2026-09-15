# Get tappable phone links in notifications

## What this solves

A visitor types `0937279861` into your form. That is a perfectly good number
locally, but a chat app cannot dial it, and WhatsApp, Telegram and Viber all
need the country code. Give the plugin your country's dialling code once and
every notification arrives with a row of one-tap contact links.

## Steps

1. Open **Well Web Notify → Settings** and click **Message** in the index on the left.
2. In **Country dialling code**, type the digits for your country — `380` for
   Ukraine, `46` for Sweden, `1` for the USA and Canada, `44` for the United
   Kingdom. The `+` is already there.
3. Press **Save changes**.

## What success looks like

The example beside the field updates as you type, showing exactly what will
happen:

> A visitor typing
> `0937279861` → **+380937279861**

And every notification that contains a phone number now ends with:

> ☎ `+380937279861`
>
> 💬 WhatsApp   ✈ Telegram   📱 Viber

Tapping any of the three opens that conversation with the number already
filled in.

## If it doesn't work

**No contact links appear at all.** The plugin did not recognise the field as a
phone number. It matches on the field's label — `Phone`, `Телефон`, `Telefon`,
`Téléphone` and around thirty others — and falls back to recognising a
phone-shaped value. Renaming the form field to something containing the word
"phone" in your language is the quickest fix.

**The number comes out wrong.** If your form has its own country-code field,
the plugin uses that and ignores this setting. If a visitor types the number
with `+` or `00` already, that is used as-is.

**It works for some submissions and not others.** A number shorter than seven
digits or longer than fifteen is not treated as a phone number, because those
are usually reference numbers or dates.
