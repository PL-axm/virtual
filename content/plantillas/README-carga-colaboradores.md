# Cómo dar de alta a una empresa y a sus colaboradores

Escrito para: quien administra la plataforma (no requiere saber programar, salvo el paso 1).

## Paso 1 — Crear la empresa (una sola vez por cliente)

Por SSH, en el servidor:

```bash
cd /home/moralesbilma/public_html/virtual.enelmapa.co
/opt/alt/php83/usr/bin/php scripts/crear-empresa.php \
  --nombre="Textiles Andinos S.A.S" \
  --codigo=TEXTILES \
  --cursos=COM-ASE
```

- `--nombre`: como se verá en la plataforma.
- `--codigo`: corto, sin espacios ni tildes. Es el que se usa en el archivo de carga.
- `--cursos`: códigos de los cursos que contrató, separados por coma.

Con eso, cualquier persona que quede en esa empresa se inscribe sola en esos cursos.

¿La empresa contrató otro curso después? Vuelve a ejecutar el mismo comando agregando el nuevo código a `--cursos`. No se duplica nada.

## Paso 2 — Preparar el archivo de colaboradores

Copia `colaboradores.csv` y edítalo en Excel o en el Bloc de notas. Una fila por persona:

| Columna | Qué va | Obligatorio |
|---|---|---|
| `username` | Usuario para entrar. Sin mayúsculas, sin espacios. Ej: `ana.gomez` | Sí |
| `firstname` | Nombre | Sí |
| `lastname` | Apellido | Sí |
| `email` | Correo. No puede repetirse entre usuarios | Sí |
| `password` | Contraseña temporal. Mínimo 8 caracteres, con mayúscula, número y símbolo | Sí |
| `city` | Ciudad | No |
| `cohort1` | El **código** de la empresa del paso 1. Ej: `TEXTILES` | Sí |

Guarda el archivo como **CSV UTF-8**. Si usas Excel: *Archivo → Guardar como → CSV UTF-8 (delimitado por comas)*.

## Paso 3 — Subir el archivo

1. Entra a la plataforma como administrador.
2. Ve a **Administración del sitio → Usuarios → Subir usuarios**.
3. Arrastra el archivo CSV y confirma que la codificación sea **UTF-8** y el delimitador, **coma**.
4. En la pantalla siguiente:
   - *Tipo de subida*: **Agregar nuevos usuarios solamente**.
   - *Nueva contraseña de usuario*: **Campo requerido en el archivo**.
   - *Forzar cambio de contraseña*: **Sí**. Así cada persona define su propia contraseña la primera vez.
5. Revisa la vista previa y confirma.

Al terminar, cada colaborador queda creado y ya inscrito en los cursos de su empresa.

## Paso 4 — Entregar los accesos

Mientras el envío de correos no esté configurado con SMTP, los mensajes automáticos de Moodle pueden no llegar. Entrega tú las credenciales al contacto de la empresa:

- Dirección: https://virtual.enelmapa.co
- Usuario y contraseña temporal de cada persona (los del archivo).
- Aviso de que el sistema pedirá cambiar la contraseña al entrar.

## Paso 5 — Nombrar al coordinador de la empresa (opcional)

Si el cliente quiere ver el avance de su equipo:

1. Entra al curso → **Participantes**.
2. **Matricular usuarios** → elige a la persona → rol **Coordinador de empresa**.

Ese rol permite ver participantes, avance y calificaciones, pero no editar el curso ni calificar.

## Errores frecuentes

- **"El nombre de usuario ya existe":** esa persona ya está creada. Cambia el tipo de subida a *Agregar nuevos y actualizar existentes* solo si quieres modificar sus datos.
- **Las tildes salen raras:** el archivo no quedó en UTF-8. Vuelve a guardarlo como CSV UTF-8.
- **"Contraseña no válida":** debe tener al menos 8 caracteres, una mayúscula, un número y un símbolo.
- **La persona entra pero no ve el curso:** revisa que `cohort1` tenga el código exacto de la empresa, en mayúsculas.
