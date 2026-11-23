import time
import cv2
import numpy as np
import serial

# Cambia este número (por ejemplo 1 o 2) según el índice que corresponda a DroidCam
INDICE_DROIDCAM = 1
PUERTO = 'COM3'
BAUDIOS = 9600

rangos = {
    'R': [
        (np.array([0, 130, 80]), np.array([10, 255, 255])),
        (np.array([170, 130, 80]), np.array([180, 255, 255])),
    ],
    'A': [
        (np.array([22, 130, 120]), np.array([32, 255, 255])),
    ],
}
nombres = {'R': 'Rojo', 'A': 'Amarillo'}

AREA_MIN = 12000
ESTABILIDAD_FRAMES = 5
COOLDOWN_SEG = 3.0


def abrir_serial(puerto, baudios):
    try:
        esp = serial.Serial(puerto, baudios, timeout=0.1)
        time.sleep(2)
        print(f"Conectado al ESP32 en {puerto}.")
        return esp
    except serial.SerialException as e:
        print(f"Aviso: no se pudo abrir {puerto} ({e}).")
        return None


def abrir_camara(indice):
    cap = cv2.VideoCapture(indice, cv2.CAP_DSHOW)
    if not cap.isOpened():
        cap.release()
        cap = cv2.VideoCapture(indice, cv2.CAP_MSMF)
    if not cap.isOpened():
        cap.release()
        cap = cv2.VideoCapture(indice)
    return cap


esp = abrir_serial(PUERTO, BAUDIOS)
cap = abrir_camara(INDICE_DROIDCAM)

if cap is None or not cap.isOpened():
    print(f"No se pudo abrir la cámara en el índice {INDICE_DROIDCAM}.")
    if esp and esp.is_open:
        esp.close()
    raise SystemExit(1)

print(f"Cámara abierta en índice {INDICE_DROIDCAM}. Presiona 's' para salir.")

ultimo = None
estable = 0
cooldown = 0.0

try:
    while True:
        ret, frame = cap.read()
        if not ret or frame is None or frame.size == 0:
            time.sleep(0.05)
            if cv2.waitKey(1) & 0xFF == ord('s'):
                break
            continue

        hsv = cv2.cvtColor(frame, cv2.COLOR_BGR2HSV)

        mejor = None
        area_max = AREA_MIN

        for letra, lista in rangos.items():
            mask = np.zeros(hsv.shape[:2], dtype=np.uint8)
            for lo, hi in lista:
                mask = cv2.bitwise_or(mask, cv2.inRange(hsv, lo, hi))
            area = cv2.countNonZero(mask)
            if area > area_max:
                mejor = letra
                area_max = area

        estable = estable + 1 if mejor == ultimo else 0
        ultimo = mejor

        ahora = time.time()
        if mejor and estable >= ESTABILIDAD_FRAMES and ahora > cooldown:
            cooldown = ahora + COOLDOWN_SEG
            print(f"Detectado: {nombres[mejor]} | Área: {area_max}")
            if esp and esp.is_open:
                try:
                    esp.write(mejor.encode())
                    print(f"Enviado al ESP32: {mejor}")
                except serial.SerialException as e:
                    print(f"Error al enviar por serial: {e}")

        cv2.putText(
            frame,
            nombres.get(mejor, '-'),
            (10, 30),
            cv2.FONT_HERSHEY_SIMPLEX,
            1,
            (0, 255, 0),
            2,
        )
        cv2.imshow('DroidCam', frame)

        if cv2.waitKey(1) & 0xFF == ord('s'):
            break
finally:
    if esp and esp.is_open:
        try:
            esp.write(b'C')
        except serial.SerialException:
            pass
        esp.close()
    if cap is not None:
        cap.release()
    cv2.destroyAllWindows()