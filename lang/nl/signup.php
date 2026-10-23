<?php

// Public sign-up form (/aanmelden). To add a language: copy this file to lang/{locale}/signup.php and translate the values.
return [

    'subtitle' => 'Lid worden',
    'title' => 'Lid worden',
    'optional' => '(optioneel)',

    'closed_title' => 'Aanmelden is op dit moment niet mogelijk',
    'closed' => 'Neem voor een lidmaatschap contact op met de ledenadministratie van :association.',

    'heading' => 'Word lid van :association',
    'intro_online' => 'Vul het formulier in en betaal daarna direct met :methods.',
    'intro_transfer' => 'Vul het formulier in. U ontvangt daarna per e-mail de betaalgegevens.',
    'intro_card' => 'Zodra uw betaling binnen is, sturen wij uw lidmaatschapskaart per e-mail.',
    'club' => 'Met dit formulier wordt u lid direct lid van Stichting Boulodrôme Maastricht.',
    'collective_clubs' => 'Bent u lid van :clubs? Dan hoeft u zich hier niet aan te melden: dat regelt uw eigen vereniging.',
    'fee' => 'Contributie bij aanmelding vandaag: <strong>:summary</strong>.',
    'runs_until' => 'Het lidmaatschap loopt tot en met 31 december.',
    'runs_until_next_year' => 'Het lidmaatschap loopt tot en met 31 december van volgend jaar.',
    'check_fields' => 'Controleer de gemarkeerde velden.',
    'honeypot' => 'Laat dit veld leeg',

    'sections' => [
        'personal' => 'Persoonsgegevens',
        'contact' => 'Contact',
        'address' => 'Adres',
        'privacy' => 'Privacy',
    ],

    'fields' => [
        'first_name' => 'Voornaam',
        'middle_name' => 'Tussenvoegsel',
        'last_name' => 'Achternaam',
        'birthday' => 'Geboortedatum',
        'birthday_placeholder' => 'dd-mm-jjjj',
        'birthday_hint' => 'Bijv. 10-09-1992',
        'email' => 'E-mailadres',
        'email_hint' => 'Hierop ontvangt u de bevestiging en uw lidmaatschapskaart.',
        'phone_mobile' => 'Mobiel nummer',
        'phone_home' => 'Telefoon thuis',
        'street' => 'Straat en huisnummer',
        'postal_code' => 'Postcode',
        'city' => 'Woonplaats',
        'country' => 'Land',
    ],

    // Stored values stay as they are (keys); only the labels are translated.
    'countries' => [
        'Nederland' => 'Nederland',
        'België' => 'België',
        'Duitsland' => 'Duitsland',
        'Anders' => 'Anders',
    ],

    'privacy' => 'Wij gebruiken uw gegevens alleen voor de ledenadministratie: uw lidmaatschap, de contributie, uw lidmaatschapskaart en berichten over de vereniging. Wij delen ze niet met anderen. U kunt uw gegevens altijd laten inzien, aanpassen of verwijderen via de ledenadministratie.',
    'consent' => 'Ik ga ermee akkoord dat :association mijn gegevens hiervoor gebruikt.',
    'submit_pay' => 'Aanmelden en betalen',
    'submit' => 'Aanmelden',

    'validation' => [
        'email_incomplete' => 'Dit e-mailadres lijkt onvolledig. Controleer het deel na de @ (bijv. naam@voorbeeld.nl).',
        'birthday_invalid' => 'Vul een geldige datum in, bijvoorbeeld 10-09-1992.',
        'birthday_check' => 'Controleer uw geboortedatum.',
        'consent' => 'U moet akkoord gaan met het gebruik van uw gegevens voor de ledenadministratie.',
        'too_many' => 'Er zijn te veel aanmeldingen vanaf uw internetverbinding. Probeer het over een uur opnieuw.',
    ],

    'thanks' => [
        'title' => 'Bedankt voor uw aanmelding',
        'heading_named' => 'Bedankt voor uw aanmelding, :name!',
        'heading' => 'Bedankt voor uw aanmelding!',
        'number' => 'Uw lidnummer is <strong>:number</strong>. Wij hebben de betaalgegevens ook naar <strong>:email</strong> gestuurd.',
        'mail_follows' => 'U ontvangt binnen enkele minuten een e-mail met de betaalgegevens.',
        'pay_title' => 'Contributie betalen',
        'amount' => ':description: <strong>:amount</strong>.',
        'amount_transfer' => ':description: <strong>:amount</strong>. Wilt u dit bedrag overmaken naar:',
        'pay_online' => 'Betalen met :methods',
        'or_transfer' => 'Liever overmaken? Dat kan naar:',
        'iban' => 'Rekeningnummer',
        'holder' => 'Ten name van',
        'amount_label' => 'Bedrag',
        'reference' => 'Omschrijving',
        'card_follows' => 'Zodra wij uw betaling hebben ontvangen, sturen wij uw lidmaatschapskaart per e-mail.',
    ],

];
