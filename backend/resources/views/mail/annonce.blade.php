<x-mail::message>
# Nouvelle annonce

Bonjour {{ $destinataire->prenom }},

**{{ $annonce->titre }}** vient d'être publiée dans **{{ $annonce->espace->nom }}**.

{{ $annonce->contenu }}

<x-mail::button :url="rtrim(config('app.frontend_url'), '/').'/espaces/'.$annonce->espace_id.'/annonces'">
Voir l'annonce
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
