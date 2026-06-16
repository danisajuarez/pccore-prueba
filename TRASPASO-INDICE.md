# 📋 Índice de Traspaso — Estado de los 3 proyectos

Documento maestro del traspaso de credenciales tras la salida del desarrollador
anterior. Cada proyecto tiene su propio `TRASPASO-KEYS.md` con el detalle.

## Resumen por proyecto

| Proyecto | Ubicación | ¿Rotar keys? | Doc detallada |
|----------|-----------|--------------|----------------|
| **PCCore** (sync WooCommerce/ML) | `pccore-prueba` | 🔴 **Sí, obligatorio** (estaban en git) | `TRASPASO-KEYS.md` |
| **OCR** (facturas) | `C:\xampp\htdocs\ocr` | 🔴 **Sí** Vision; revocar el resto | `TRASPASO-KEYS.md` |
| **Credicoop** (API banco) | `C:\laragon\www\credicoop` | 🟢 No (CUIT de la empresa) | `TRASPASO-KEYS.md` |

## Qué se hizo en este traspaso

1. **Se sacaron todas las keys del código** y se centralizaron:
   - PCCore → `php/config/secrets.php` (gitignored)
   - OCR → `.env` (ya existía; se corrigió `api/ocr.php` que tenía una hardcodeada)
   - Credicoop → ya estaba todo en `.env` + `storage/keys/`
2. **Se protegió todo con `.gitignore`** en los tres proyectos.
3. **Se verificó el historial de git**: las keys de PCCore (ML + Woo) y la de
   Google Vision de OCR quedaron en commits viejos → por eso esas se deben ROTAR.
4. **Se documentó cada proyecto** con su guía y checklist de revocación.

## Orden recomendado de ejecución (para el ex-jefe / nuevo dev)

1. Leer el `TRASPASO-KEYS.md` de cada proyecto.
2. Crear las cuentas propias y obtener las nuevas keys.
3. Cargarlas en `secrets.php` (PCCore) y `.env` (OCR, Credicoop).
4. Probar que cada app funcione.
5. Avisar al desarrollador anterior para que **recién entonces** revoque las suyas
   (checklist al final de cada `TRASPASO-KEYS.md`).

## Nota sobre el historial de git (PCCore y OCR)

Sacar las keys del código no las borra de los commits anteriores. Las keys
afectadas hay que **rotarlas** (generar nuevas), no solo moverlas. Si además se
quiere limpiar el historial, se puede usar `git filter-repo`, pero **lo seguro y
suficiente es rotar las keys** — una vez rotadas, las que quedaron en el
historial dejan de servir.
