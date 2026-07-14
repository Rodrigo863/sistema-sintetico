# Publicar con ngrok

Este proyecto incluye un script para probarlo por internet con ngrok free.

## Uso rapido

1. Abre XAMPP o deja que el script intente iniciarlo.
2. Ejecuta:

```powershell
.\publicar_ngrok.bat
```

El script:

- Copia el proyecto a `C:\xampp\htdocs\proyecto sintetico`.
- Intenta iniciar Apache y MySQL.
- Importa `schema.sql` si la base todavia no esta preparada.
- Abre `ngrok http 80`.
- Muestra el link final del sistema y el link publico de reservas.

## Requisito de ngrok

Si ngrok no esta instalado, descargalo desde:

```text
https://ngrok.com/download
```

Luego inicia sesion en ngrok, copia tu token y ejecuta una sola vez:

```powershell
ngrok config add-authtoken TU_TOKEN
```

Despues vuelve a correr:

```powershell
.\publicar_ngrok.bat
```

Si lo descargaste y quedo en:

```text
C:\Users\User\Downloads\ngrok-v3-stable-windows-amd64\ngrok.exe
```

no hace falta moverlo: el script tambien lo busca ahi.

## Links que genera

Sistema completo:

```text
https://TU-LINK.ngrok-free.app/proyecto%20sintetico/
```

Reservas publicas:

```text
https://TU-LINK.ngrok-free.app/proyecto%20sintetico/reservas_publicas.php
```

## Opciones utiles

No importar la base:

```powershell
.\publicar_ngrok.bat -SkipDatabase
```

No copiar a `htdocs`:

```powershell
.\publicar_ngrok.bat -SkipCopy
```

Usar otro XAMPP:

```powershell
.\publicar_ngrok.bat -XamppPath "D:\xampp"
```

## Seguridad

Antes de compartir el link, cambia la clave inicial:

```text
administrador / admin123
```

Con ngrok free el link cambia cada vez que cierres y abras ngrok.
