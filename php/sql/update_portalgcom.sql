-- Actualizar Portalgcom para usar BD SIGE de Digital Pergamino
-- Así tiene tablas completas pero sube a WC de prueba

UPDATE sige_two_terwoo
SET
    TWO_ServidorDBAnt = 'giuggia.dyndns-home.com',
    TWO_PuertoDBAnt = 3307,
    TWO_UserDBAnt = 'root',
    TWO_PassDBAnt = 'giuggia',
    TWO_NombreDBAnt = 'giuggia'
WHERE TER_IdTercero = 2;
