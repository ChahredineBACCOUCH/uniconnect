<x-mail::message>
# Activez votre compte UniConnect

Bonjour {{ $user->prenom }} {{ $user->nom }},

Votre compte a été créé et attend d'être **activé**. Choisissez votre mot de passe en cliquant sur le bouton ci-dessous :

<x-mail::button :url="$lien">
Activer mon compte
</x-mail::button>

Ce lien est **valable 48 heures**. S'il a expiré, connectez-vous pour recevoir un nouveau lien d'activation.

Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :

{{ $lien }}

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
