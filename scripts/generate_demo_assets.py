"""Original vector-style garment illustrations for local catalog demonstrations.
Requires Pillow. Does not use or modify client photography.
"""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1] / 'database' / 'demo-images'
ROOT.mkdir(parents=True, exist_ok=True)
F = Path('C:/Windows/Fonts/segoeui.ttf')
FONT = ImageFont.truetype(str(F), 23)
SMALL = ImageFont.truetype(str(F), 17)
ITEMS = [
    ('filipina-esencial', 'Filipina esencial', 'top', [('blanco', '#ecebe6'), ('cafe', '#795548')]),
    ('filipina-balance', 'Filipina Balance', 'top', [('marino', '#193758'), ('verde', '#438379')]),
    ('bata-clinica', 'Bata clínica', 'coat', [('blanco', '#f0efeb'), ('azul', '#6197be')]),
    ('pantalon-clinico', 'Pantalón clínico', 'pants', [('marino', '#193758'), ('negro', '#30343b')]),
    ('gorro-clinico', 'Gorro clínico', 'cap', [('azul', '#457ea4'), ('verde', '#438379')]),
    ('conjunto-quirurgico', 'Conjunto quirúrgico', 'set', [('marino', '#193758'), ('cafe', '#795548')]),
    ('bata-medica', 'Bata médica', 'coat', [('blanco', '#f0efeb'), ('gris', '#9ca6b0')]),
    ('camisa-industrial', 'Camisa industrial', 'shirt', [('azul', '#35678c'), ('gris', '#818e9c')]),
    ('overol-industrial', 'Overol industrial', 'overall', [('marino', '#193758'), ('gris', '#818e9c')]),
    ('chaleco-seguridad', 'Chaleco de seguridad', 'vest', [('naranja', '#e99239'), ('amarillo', '#d5c642')]),
]

for slug, label, kind, colors in ITEMS:
    for color_name, color in colors:
        image = Image.new('RGB', (720, 900), '#f2f5f9')
        draw = ImageDraw.Draw(image)
        draw.rounded_rectangle((28, 28, 692, 872), radius=24, fill='#ffffff')
        draw.text((60, 59), 'NEVA MEDIC / COLECCIÓN DEMO', font=SMALL, fill='#627085')
        draw.ellipse((165, 705, 555, 746), fill='#e6eaf0')
        outline = '#475569'
        def polygon(points, fill=color):
            draw.polygon(points, fill=fill)
            draw.line(points + [points[0]], fill=outline, width=3, joint='curve')
        def top(y=195, height=375, width=1):
            points = [(270,y),(225,y+22),(145,y+100),(205,y+167),(247,y+132),(243,y+height),(477,y+height),(473,y+132),(515,y+167),(575,y+100),(495,y+22),(450,y),(402,y+10),(360,y+60),(318,y+10)]
            polygon(points)
            draw.line([(318,y+10),(360,y+60),(402,y+10)], fill='#c7d1dd', width=7)
            draw.rounded_rectangle((385,y+105,445,y+178), radius=7, outline=outline, width=3)
            draw.line([(265,y+height-18),(455,y+height-18)], fill=outline, width=2)
        if kind in ('top', 'shirt'):
            top()
            if kind == 'shirt':
                draw.line((360,260,360,570), fill=outline, width=4)
                for y in range(280,550,45): draw.ellipse((356,y,364,y+8),fill='#dce2ea')
        elif kind == 'coat':
            polygon([(275,165),(230,185),(175,365),(218,390),(252,300),(225,690),(495,690),(468,300),(502,390),(545,365),(490,185),(445,165),(360,228)])
            polygon([(275,165),(360,228),(319,326),(287,252)], '#dce2ea')
            polygon([(445,165),(360,228),(401,326),(433,252)], '#dce2ea')
            draw.line((360,230,360,680), fill=outline, width=3)
            for x in (260,400): draw.rectangle((x,425,x+60,500), outline=outline, width=3)
        elif kind == 'pants':
            polygon([(252,190),(468,190),(490,680),(390,690),(360,380),(330,690),(230,680)])
            draw.rectangle((253,191,467,220), fill=outline)
            draw.line((360,220,360,330), fill='#dce2ea', width=3)
        elif kind == 'cap':
            draw.ellipse((175,240,545,590),fill=color,outline=outline,width=3)
            draw.rounded_rectangle((175,440,545,555),radius=25,fill=color,outline=outline,width=3)
            draw.line((187,505,533,505),fill='#dce2ea',width=5)
        elif kind == 'set':
            top(150,270)
            polygon([(275,445),(445,445),(465,695),(380,695),(360,540),(340,695),(255,695)])
        elif kind == 'overall':
            polygon([(285,180),(240,200),(180,360),(225,389),(266,315),(248,690),(330,690),(360,505),(390,690),(472,690),(454,315),(495,389),(540,360),(480,200),(435,180),(360,235)])
            draw.line((360,235,360,490), fill='#dce2ea', width=6)
            for x in (284,383): draw.rectangle((x,310,x+50,370),outline=outline,width=3)
        elif kind == 'vest':
            polygon([(285,200),(240,220),(250,330),(215,370),(235,650),(485,650),(505,370),(470,330),(480,220),(435,200),(360,320)])
            draw.line((360,320,360,645),fill=outline,width=6)
            draw.rectangle((238,490,482,521),fill='#e8edf2')
            draw.rectangle((238,549,482,580),fill='#e8edf2')
        draw.text((60, 774), label, font=FONT, fill='#0c2f62')
        draw.text((60, 811), f'{color_name.upper()} · Ilustración de muestra', font=SMALL, fill='#627085')
        image.save(ROOT / f'{slug}-{color_name}.webp', 'WEBP', quality=88, method=6)
print(f'Generated {len(ITEMS) * 2} original WebP illustrations.')
