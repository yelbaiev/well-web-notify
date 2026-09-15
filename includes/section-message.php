<?php
/**
 * Message — how a notification reads when it arrives.
 *
 * The country code lived under Sources, which was filing it by the screen that
 * had room. It is not a trigger: it is how the phone number gets rendered in
 * the message that gets sent. Putting it here also gives the next "what goes in
 * the message" option somewhere obvious to land.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wellweb_notify_section_message(): void {
    $code = (string) wellweb_notify_get_option( 'default-country-code', '' );
    ?>
    <p class="wpds-panel__intro">
        <?php esc_html_e( 'A message carries the form name, the answers, and — when there is a phone number — a row of one-tap links to call, WhatsApp, Telegram or Viber. Those links need the number in international form, and most visitors type theirs without the country code.', 'well-web-notify' ); ?>
    </p>

    <div class="wellweb-notify-phone">
        <div class="wellweb-notify-phone__field">
            <label for="f-country-code"><?php esc_html_e( 'Country dialling code', 'well-web-notify' ); ?></label>
            <div class="wellweb-notify-phone__input">
                <span aria-hidden="true">+</span>
                <input type="number" id="f-country-code" class="wpds-input"
                       name="well-web-notify-default-country-code"
                       value="<?php echo esc_attr( $code ); ?>"
                       placeholder="380" min="1" max="999" inputmode="numeric" />
            </div>
            <span class="wpds-fields__hint">
                <?php esc_html_e( '380 Ukraine · 46 Sweden · 44 United Kingdom · 1 USA and Canada', 'well-web-notify' ); ?>
            </span>
        </div>

        <?php
        // A worked example beats the paragraph explaining it, and it updates as
        // you type — so the setting proves itself before anyone waits for a
        // real submission to find out.
        $example = $code !== '' ? wellweb_notify_normalize_phone( '0937279861' ) : '';
        ?>
        <div class="wellweb-notify-phone__preview" data-example="0937279861">
            <div class="wpds-fields__hint"><?php esc_html_e( 'A visitor typing', 'well-web-notify' ); ?></div>
            <div class="wellweb-notify-phone__pair">
                <code>0937279861</code>
                <span aria-hidden="true">&rarr;</span>
                <code class="wellweb-notify-phone__out"><?php
                    echo esc_html( $example !== '' ? $example : __( 'set a code', 'well-web-notify' ) );
                ?></code>
            </div>
        </div>
    </div>

    <p class="wpds-fields__hint wellweb-notify-phone__note">
        <?php esc_html_e( 'A number the visitor already typed with a country code, and a form with its own country-code field, are both left alone.', 'well-web-notify' ); ?>
    </p>
    <?php
}
