# 🔑 Guía de Traspaso de Credenciales

Este documento lista **todas las API keys y credenciales** que hay que reemplazar
por cuentas propias de la empresa, porque las actuales pertenecen a la cuenta
personal del desarrollador anterior.

> **Regla de oro del traspaso:**
> 1. La empresa crea sus propias cuentas y obtiene las nuevas credenciales.
> 2. Se reemplazan en los archivos indicados abajo.
> 3. Se prueba que todo funcione.
> 4. **Recién entonces** el desarrollador anterior revoca/borra las suyas.
>
> Si se revocan antes de poner las nuevas, las apps dejan de funcionar.

> ## ⚠️ IMPORTANTE: estas keys están en el historial de git
>
> Se verificó el historial de commits y se confirmó que las siguientes keys
> **quedaron registradas en commits anteriores** (aunque ahora ya no estén en
> el código actual):
>
> - PCCore → Mercado Libre Client Secret y WooCommerce `ck_/cs_`
> - OCR → Google Vision key que estaba hardcodeada en `api/ocr.php`
>
> Sacarlas del código **no las borra del historial**: cualquiera con acceso al
> repo puede recuperarlas de commits viejos. Por eso, para estas keys **no
> alcanza con moverlas: HAY QUE ROTARLAS (generar nuevas) obligatoriamente.**
>
> Las keys del `.env` de OCR (Anthropic, Groq, Gemini, OCR.space) **nunca se
> commitearon**, así que para esas alcanza con revocar y reemplazar.

---

## 📦 Proyecto 1 — PCCore (sincronizador WooCommerce / Mercado Libre)

Las credenciales ya **no están en el código**. Viven en un único archivo:

    php/config/secrets.php   ← (no se sube a git)

Para configurarlo:

1. Copiar la plantilla:

       cp php/config/secrets.example.php php/config/secrets.php

2. Editar `secrets.php` y completar con las credenciales propias (ver abajo).

### 1.1 Mercado Libre

| Dato | Valor anterior (REEMPLAZAR) |
|------|------------------------------|
| App ID | `828139284413193` |
| Client Secret | `Xeru5mcUpEtxFwoDeLjvAh2qsQYspzLP` (también existía `zkXFOW1IOODosHBEkeJmjBKLCzG9AFq2`) |

**Cómo obtener las propias:**
1. Entrar a https://developers.mercadolibre.com.ar con la cuenta de ML de la empresa.
2. Crear una aplicación nueva ("Crear nueva aplicación").
3. Copiar el **App ID** y el **Client Secret**.
4. Pegarlos en `secrets.php` (`ML_APP_ID` y `ML_CLIENT_SECRET`).

### 1.2 WooCommerce (tienda pccore.com.ar)

| Dato | Valor anterior (REEMPLAZAR) |
|------|------------------------------|
| Consumer Key | `ck_28e04bbb3d5000fb9240cac6bb64ad2597aff0df` |
| Consumer Secret | `cs_b6442994d793997f0f9c829b8cdf3c38b3231c28` |

> Estas keys son de la propia tienda WordPress de la empresa, así que probablemente
> NO haya que cambiarlas — pero conviene **regenerarlas** ya que estuvieron expuestas.

**Cómo regenerarlas:**
1. WordPress Admin → **WooCommerce → Ajustes → Avanzado → API REST**.
2. Revocar la key vieja y crear una nueva (permisos: Lectura/Escritura).
3. Pegar la nueva `ck_...` y `cs_...` en `secrets.php`.

---

## 🧾 Proyecto 2 — OCR de Facturas (`C:\xampp\htdocs\ocr`)

Las credenciales ya están centralizadas en el archivo `.env` (no se sube a git).
Plantilla en `.env.example`.

| Variable | Servicio | Dónde obtener la propia |
|----------|----------|--------------------------|
| `ANTHROPIC_API_KEY` | Claude (Anthropic) | https://console.anthropic.com → API Keys |
| `GOOGLE_VISION_API_KEY` | Google Cloud Vision | https://console.cloud.google.com → APIs y servicios → Credenciales |
| `GEMINI_API_KEY` | Google Gemini | https://aistudio.google.com/apikey |
| `GROQ_API_KEY` | Groq | https://console.groq.com/keys |
| `OCRSPACE_API_KEY` | OCR.space | https://ocr.space/ocrapi (registro gratuito) |

> ⚠️ La key de Google Vision también estaba **hardcodeada** en `api/ocr.php`.
> Ya se corrigió: ahora también lee desde `.env`. Solo hay que poner la nueva
> key en `GOOGLE_VISION_API_KEY`.

### Base de datos (OCR)
El `.env` tenía una conexión a `remoto.retec.com.ar` con usuario `danisa` / `danisa2025`.
Si esa cuenta de BD es personal del desarrollador, pedir al admin de la BD una
cuenta propia y actualizar `PRUEBA_DB_USER` / `PRUEBA_DB_PASS`.

---

## 🏦 Proyecto 3 — Credicoop (`c:\laragon\www\credicoop`)

Credenciales en `.env` y clave privada en `storage/keys/*.pem` (ambos fuera de git).

| Dato | Valor |
|------|-------|
| `CREDICOOP_CLIENT_ID` | `30695392334` (CUIT) |
| `CREDICOOP_ADHERENT_ID` | `257419` |
| Clave privada | `storage/keys/30695392334_HOMOprivate.pem` |
| Entorno | **Homologación** (sandbox del banco) |

> Esto está a nombre del **CUIT 30695392334**. Si ese CUIT es de la empresa,
> NO hay que cambiar nada. Si es personal del desarrollador, hay que tramitar
> el alta de API Empresas de Credicoop con el CUIT de la empresa y generar un
> nuevo par de claves.

---

## ✅ Checklist de revocación (para el desarrollador que se va)

Hacer esto **DESPUÉS** de confirmar que la empresa ya puso sus propias keys:

- [ ] **Anthropic** — revocar `sk-ant-...` en https://console.anthropic.com
- [ ] **Google Cloud** — revocar las 2 keys de Vision + la de Gemini en https://console.cloud.google.com
- [ ] **Groq** — revocar `gsk_...` en https://console.groq.com/keys
- [ ] **OCR.space** — regenerar key
- [ ] **Mercado Libre** — resetear el Client Secret o borrar la app
- [ ] **WooCommerce** — revocar las keys `ck_/cs_` desde el admin de WordPress
- [ ] **Credicoop** — solo si el CUIT era personal
- [ ] **BD remoto.retec.com.ar** — pedir baja del usuario `danisa` si era personal
