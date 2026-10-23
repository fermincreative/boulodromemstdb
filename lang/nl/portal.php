<?php

// Ledenportaal (main domain). To add a language: copy this file to lang/{locale}/portal.php and translate the values.
return [

    'subtitle' => 'Ledenportaal',

    'login' => [
        'title' => 'Inloggen',
        'sent' => 'Als <strong>:email</strong> bij ons bekend is, ontvangt u binnen enkele minuten een e-mail met een inloglink. Kijk ook in uw map met ongewenste e-mail. De link is :minutes minuten geldig.',
        'logged_out' => 'U bent uitgelogd.',
        'intro' => 'Vul het e-mailadres in dat bij de ledenadministratie bekend is. U ontvangt een link waarmee u inlogt; een wachtwoord is niet nodig.',
        'email' => 'E-mailadres',
        'submit' => 'Stuur mij een inloglink',
        'not_a_member' => 'Nog geen lid?',
        'join' => 'Word lid van :association.',
        'sign_up' => 'Aanmelden',
        'too_many' => 'U heeft te vaak een inloglink aangevraagd. Probeer het over een uur opnieuw.',
    ],

    'login_mail' => [
        'subject' => 'Uw inloglink — :association',
        'body' => "Beste :name,\n\nU heeft gevraagd om in te loggen op het ledenportaal van :association.\n\n[Inloggen](:url)\n\nDeze link werkt één keer en is :minutes minuten geldig. Daarna blijft u op dit apparaat ingelogd.\n\nHeeft u dit niet aangevraagd? Dan kunt u deze e-mail negeren; er gebeurt dan niets.\n\nMet vriendelijke groet,\n\nLedenadministratie :association",
    ],

    'confirm' => [
        'intro' => 'Klik op de knop om in te loggen op het ledenportaal. U blijft daarna op dit apparaat ingelogd.',
        'submit' => 'Inloggen',
        'expired_title' => 'Deze inloglink werkt niet meer',
        'expired' => 'De link is verlopen of al gebruikt. Vraag een nieuwe aan.',
        'expired_error' => 'Deze inloglink is verlopen of al gebruikt. Vraag hieronder een nieuwe aan.',
        'new_link' => 'Nieuwe inloglink aanvragen',
    ],

    'dashboard' => [
        'title' => 'Mijn lidmaatschap',
        'logged_in_as' => 'Ingelogd als :email',
        'log_out' => 'Uitloggen',
        'ended' => 'Uw lidmaatschap is beëindigd per :date.',
        'member_until' => 'U bent lid tot en met <strong>:date</strong>.',
        'not_valid' => 'Op dit moment heeft u geen geldig lidmaatschap.',
        'pending_payment' => 'Lidmaatschap :year: wacht op uw betaling.',
        'pending_club' => 'Lidmaatschap :year: wordt betaald via :club.',
        'your_club' => 'uw vereniging',
        'to_pay' => 'Te betalen',
        'pay' => 'Betalen',
        'card_label' => 'Lidmaatschapskaart :year',
        'show_at_entrance' => 'Toon deze code bij de ingang',
        'card_line' => 'Lidmaatschapskaart :year · geldig t/m :date',
        'upcoming' => 'Rooster komende dagen',
        'full_schedule' => 'Bekijk het volledige rooster',
        'as_file' => 'Liever als bestand?',
        'download_phone' => 'Download voor telefoon',
        'download_print' => 'Download om te printen',
        'next_paid' => 'Uw lidmaatschap voor :year is al betaald. De kaart voor :year verschijnt hier op :date.',
        'details' => 'Uw gegevens',
        'edit' => 'Wijzigen',
        'memberships' => 'Lidmaatschappen',
        'period' => ':from t/m :until',
        'contact' => 'Klopt er iets niet in uw gegevens? Mail de ledenadministratie: :email.',
        'card_error' => 'Uw kaart kan op dit moment niet worden gemaakt. Neem contact op met de ledenadministratie.',
        'saved' => 'Uw gegevens zijn opgeslagen.',
        'email_confirmation_sent' => 'We hebben een e-mail gestuurd naar <strong>:email</strong>. Klik op de link in die e-mail om uw nieuwe e-mailadres te bevestigen; tot die tijd blijft uw oude adres in gebruik.',
        'email_changed' => 'Uw e-mailadres is gewijzigd in :email.',
    ],

    'nav' => [
        'membership' => 'Mijn lidmaatschap',
        'schedule' => 'Rooster',
    ],

    'schedule' => [
        'title' => 'Rooster',
        'week' => 'Week :number',
        'range' => ':from t/m :until',
        'intro' => 'Wanneer de Boulodrôme open is. Bij een evenement ziet u of er nog banen vrij zijn.',
        'previous' => 'Vorige week',
        'next' => 'Volgende week',
        'this_week' => 'Deze week',
        'today' => 'vandaag',
        'closed' => 'Gesloten',
        'open' => 'Open · :lanes banen',
        'open_one' => 'Open · 1 baan',
        'event' => 'Evenement',
        'lanes_free' => ':lanes banen vrij',
        'lane_free' => '1 baan vrij',
        'no_lanes_free' => 'geen vrije banen',
        'extra' => 'Extra',
        'afternoon' => 'Middag',
        'evening' => 'Avond',
    ],

    'fields' => [
        'email' => 'E-mailadres',
        'phone_mobile' => 'Mobiel',
        'phone_home' => 'Telefoon thuis',
        'street' => 'Straat en huisnummer',
        'postal_code' => 'Postcode',
        'city' => 'Woonplaats',
        'country' => 'Land',
        'address' => 'Adres',
        'joined_at' => 'Lid sinds',
        'optional' => '(optioneel)',
    ],

    'edit' => [
        'title' => 'Gegevens wijzigen',
        'intro' => 'Wijzig hier de contactgegevens van :name. Uw naam en lidnummer kan alleen de ledenadministratie aanpassen.',
        'email_hint' => 'Wijzigt u uw e-mailadres? Dan sturen wij eerst een bevestigingslink naar het nieuwe adres. Met dit adres logt u ook in.',
        'email_shared' => 'Dit e-mailadres gebruikt u samen met :names. Wijzigt u het, dan krijgt u een eigen inlog op het nieuwe adres.',
        'save' => 'Opslaan',
        'cancel' => 'Annuleren',
        'check_fields' => 'Controleer de gemarkeerde velden.',
        'too_many' => 'U heeft uw gegevens te vaak gewijzigd. Probeer het over een uur opnieuw.',
        'email_incomplete' => 'Dit e-mailadres lijkt onvolledig. Controleer het deel na de @ (bijv. naam@voorbeeld.nl).',
    ],

    'email_changed_mail' => [
        'subject' => 'Uw e-mailadres is gewijzigd — :association',
        'body' => "Beste :name,\n\nHet e-mailadres van uw lidmaatschap bij :association is zojuist via het ledenportaal gewijzigd in :email. Vanaf nu logt u met dat adres in en ontvangt u daar onze e-mails.\n\n**Heeft u dit niet zelf gedaan?** Neem dan direct contact op met de ledenadministratie via :contact, dan zetten wij het terug.\n\nMet vriendelijke groet,\n\nLedenadministratie :association",
    ],

    'email_change' => [
        'subject' => 'Bevestig uw nieuwe e-mailadres — :association',
        'body' => "Beste :name,\n\nU heeft gevraagd om uw e-mailadres bij :association te wijzigen in dit adres.\n\n[E-mailadres bevestigen](:url)\n\nDeze link is :hours uur geldig. Daarna logt u met dit adres in op het ledenportaal en ontvangt u hier onze e-mails.\n\nHeeft u dit niet aangevraagd? Dan kunt u deze e-mail negeren; er verandert dan niets.\n\nMet vriendelijke groet,\n\nLedenadministratie :association",
        'title' => 'Nieuw e-mailadres bevestigen',
        'intro' => 'Klik op de knop om het e-mailadres van :name te wijzigen in <strong>:email</strong>.',
        'submit' => 'Bevestigen',
        'invalid_title' => 'Deze bevestigingslink werkt niet meer',
        'invalid' => 'De link is verlopen of het e-mailadres is inmiddels al gewijzigd. Wijzig uw e-mailadres opnieuw via het ledenportaal.',
        'to_portal' => 'Naar het ledenportaal',
    ],

];
