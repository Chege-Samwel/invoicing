# Minimal PNG writer: draws the invoice bands at true page coordinates so the
# arrangement can be inspected without a browser or an SVG renderer.
import zlib, struct, re, sys

W_pt, H_pt = 595.0, 842.0
GAP = 24.0                                 # space drawn between sheets
S = 1.6                                    # px per pt
W, H = int(W_pt*S), int(H_pt*S)
img = bytearray(b'\xff' * (W*H*3))

def px(x, y, c):
    if 0 <= x < W and 0 <= y < H:
        i = (y*W + x)*3
        img[i:i+3] = bytes(c)

def rect(x, y, w, h, c, fill=True):
    x0, y0 = int(x*S), int(y*S)
    x1, y1 = int((x+w)*S), int((y+h)*S)
    if fill:
        for yy in range(y0, y1):
            for xx in range(x0, x1):
                px(xx, yy, c)
    else:
        for xx in range(x0, x1):
            px(xx, y0, c); px(xx, y1-1, c)
        for yy in range(y0, y1):
            px(x0, yy, c); px(x1-1, yy, c)

def hline(x0, x1, y, c, t=1):
    rect(x0, y, x1-x0, t/S, c)

def vline(x, y0, y1, c, t=1):
    rect(x, y0, t/S, y1-y0, c)

def bar(x_right, y, length, c, h=4):
    """a right-aligned figure standing in for a number"""
    rect(x_right-length, y, length, h, c)

def lb(x_left, y, length, c, h=4):
    """a left-aligned run standing in for a word"""
    rect(x_left, y, length, h, c)

html = open(sys.argv[1]).read()
bands = [(m[0], float(m[1]), float(m[2]), float(m[3]), float(m[4]))
         for m in re.findall(r'class="adv-band adv-band-(\w+)"[^>]*style="left:([\d.]+)pt;top:([\d.]+)pt;width:([\d.]+)pt;height:([\d.]+)pt;"', html)]

INK   = (26, 32, 39)
GREY  = (122, 132, 144)
LIGHT = (214, 221, 228)
ACC   = (11, 107, 203)

# letterhead: firm left, client middle, QR right
X0, Wd = W_pt*0.055, W_pt*0.89
C = Wd*0.38
# firm name + address lines (stand-ins)
lb(X0, 44, 90, INK, 7)
for i in range(4): lb(X0, 58+i*9, 74-i*7, GREY, 3)
vline(X0+C, 42, 98, LIGHT)
lb(X0+C+12, 44, 100, ACC, 7)
for i in range(3): lb(X0+C+12, 58+i*9, 86-i*9, GREY, 3)
# QR, solid, top right
rect(X0+Wd-50, 42, 48, 48, INK)

# meta rule + type + three field pairs, right aligned
hline(X0, X0+Wd, 110, LIGHT, 2)
lb(X0, 116, 52, ACC, 8)
xr = X0+Wd
for i in range(3):
    xr -= 58
    lb(xr, 114, 24, GREY, 3)
    lb(xr, 121, 42, INK, 5)
hline(X0, X0+Wd, 133, LIGHT, 2)

palette = {'items': (244, 248, 251), 'totals': (238, 246, 255),
           'signature': (248, 248, 248), 'remarks': (251, 251, 251)}
for name, l, t, w, h in bands:
    # the header band is only an outline: its contents were already drawn
    # above, and filling it here painted over them
    if name != 'header':
        rect(l, t, w, h, palette.get(name, (250, 250, 250)))
    rect(l, t, w, h, LIGHT, fill=False)

    if name == 'items':
        lb(l+8, t+12, 40, GREY, 3)
        lb(l+w-52, t+12, 44, GREY, 3)
        hline(l+8, l+w-8, t+18, LIGHT)
        # draw the rows the band actually holds - a fixed five drew a sketch
        # of a table rather than the table, and made a full band look empty
        ROW = 17.0
        at_most = max(1, int((h - 24) // ROW))
        for i in range(at_most):
            y = t + 24 + i * ROW
            lb(l+8, y, 120, INK, 4)
            lb(l+w*0.42, y, 26, GREY, 4)
            lb(l+w*0.68, y, 40, GREY, 4)
            bar(l+w-8, y, 46, INK, 4)
            if i < at_most - 1: hline(l+8, l+w-8, y+9, (236, 240, 244))
    elif name == 'totals':
        right = l+w-8
        for i in range(4):
            y = t+12+i*11
            lb(l+8, y, 44, GREY, 3)
            bar(right, y, 48, (80, 88, 96), 3)
        hline(l+8, right, t+58, LIGHT, 2)
        lb(l+8, t+66, 34, INK, 5)
        bar(right, t+62, 78, ACC, 10)       # the one loud figure
        bar(right, t+78, 20, GREY, 3)       # the currency, once
    elif name == 'signature':
        hline(l+8, l+78, t+h-12, GREY, 2)
        lb(l+8, t+h-8, 26, GREY, 3)
    elif name == 'remarks':
        lb(l+8, t+6, 30, GREY, 3)
        lb(l+8, t+14, 240, (60, 66, 72), 3)

rect(0, 0, W_pt, H_pt, (170, 178, 186), fill=False)

raw = b''.join(b'\x00' + bytes(img[y*W*3:(y+1)*W*3]) for y in range(H))
def chunk(t, d):
    c = struct.pack('>I', len(d)) + t + d
    return c + struct.pack('>I', zlib.crc32(t+d) & 0xffffffff)
png = (b'\x89PNG\r\n\x1a\n'
       + chunk(b'IHDR', struct.pack('>IIBBBBB', W, H, 8, 2, 0, 0, 0))
       + chunk(b'IDAT', zlib.compress(raw, 9))
       + chunk(b'IEND', b''))
open(sys.argv[2], 'wb').write(png)
print('wrote', sys.argv[2], W, 'x', H, 'bands:', [b[0] for b in bands])
