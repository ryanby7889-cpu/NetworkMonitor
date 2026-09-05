# Energy Monitor

Dashboard monitoring energi berbasis PHP + MySQL untuk ESP8266 + PZEM004T.

## Struktur

- `dashboard/` — dashboard realtime
- `api/` — endpoint sensor, history, statistik, dan alarm
- `config/` — konfigurasi database dan tarif energi
- `alarm/` — riwayat alarm
- `assets/` — CSS dan JavaScript
- `esp8266_energy/` — firmware ESP8266
- `energy_monitor.sql` — schema/data database

## API utama

- `api/save_data.php` — menerima pembacaan sensor
- `api/latest.php` — data realtime + status online/offline
- `api/history.php?limit=60` — histori sensor
- `api/stats.php` — ringkasan alarm dan data terakhir
- `api/alarm_status.php` — evaluasi alarm berdasarkan setting
- `api/update_alarm.php` — acknowledge alarm

## Konfigurasi

Database dapat dikonfigurasi menggunakan environment variable `DB_HOST`, `DB_USER`, `DB_PASS`, dan `DB_NAME`. Tarif energi dapat diatur melalui `ENERGY_TARIFF`; nilai default adalah Rp 1.500/kWh.

## Status pengembangan

Branch aktif: `energy-monitor-development`.
