-- Tabla para parámetros generales del sistema
CREATE TABLE IF NOT EXISTS parametros (
    nombre VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(100) NOT NULL
);
