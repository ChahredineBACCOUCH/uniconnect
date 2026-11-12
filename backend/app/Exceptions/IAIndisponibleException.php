<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Gemini et Groq ont tous les deux échoué : l'API renvoie 503
 * « L'assistant est momentanément indisponible » (SCRUM-102).
 */
class IAIndisponibleException extends RuntimeException {}
