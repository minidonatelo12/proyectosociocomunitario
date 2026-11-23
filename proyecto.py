import time
import cv2
import numpy as np
from PIL import ImageGrab
import serial

PUERTO = "COM3"
BAUDIOS = 115200

# Coloca aquí la ventana de DroidCam (x1, y1, x2, y2) y deja esta zona libre de otras ventanas
REGION_PANTALLA = (20, 60, 420, 360)

rangos = {
    "R": [
        (np.array([0, 130, 80]), np.array([10, 255, 255])),
        (np.array([170, 130, 80]), np.array([180, 255, 255])),
    ],
    "A": [
        (np.array([22, 130, 120]), np.array([32, 255, 255])),
    ],
}
nombres = {"R": "Rojo", "A": "Amarillo"}

AREA_MIN = 12000
ESTABILIDAD_FRAMES = 5
COOLDOWN_SEG = 3.0

esp = serial.Serial(PUERTO, BAUDIOS, timeout=0.2)
time.sleep(1.5)
print("ESP32 conectado en", PUERTO)

cv2.namedWindow("Clasificador", cv2.WINDOW_NORMAL)
cv2.resizeWindow("Clasificador", 420, 320)
cv2.moveWindow("Clasificador", 680, 60)

ultimo = None
estable = 0
cooldown = 0.0

print("Listo. Pon DroidCam en (20, 60, 420, 360) y presiona 's' para salir.")
try:
    while True:
        img = ImageGrab.grab(bbox=REGION_PANTALLA)
        frame = cv2.cvtColor(np.array(img), cv2.COLOR_RGB2BGR)
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
            esp.write(mejor.encode())

        cv2.putText(
            frame,
            nombres.get(mejor, "-"),
            (10, 30),
            cv2.FONT_HERSHEY_SIMPLEX,
            1,
            (0, 255, 0),
            2,
        )
        cv2.imshow("Clasificador", frame)

        if cv2.waitKey(40) & 0xFF == ord("s"):
            break
finally:
    if esp and esp.is_open:
        esp.write(b"C")
        esp.close()
    cv2.destroyAllWindows()
    print("Cerrado limpio.")