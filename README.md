# Bricks Email Templates

WordPress plugin pro formátování emailů z Bricks Builder formulářů pomocí **vizuálního Email Builderu** nebo externích HTML šablon.

## 🎯 Proč tento plugin?

Bricks Builder má skvělý formulářový builder, ale formátování emailů v malém textovém poli je nepraktické. Tento plugin ti umožní:

- ✅ **Vizuální Email Builder** - Vytvoř krásné emaily bez psaní HTML
- ✅ **Color picker** - Vyber barvy pro header, akcenty a pozadí
- ✅ **3 layouty** - Kompaktní, Moderní, Prostorný
- ✅ **Upload loga** - Přidej logo do emailu
- ✅ **Vlastní texty** - Nastav hlavičku, úvod a patičku
- ✅ **Live preview** - Okamžitý náhled šablony
- ✅ **Automaticky načítat všechna pole** pomocí `{{all_fields}}`
- ✅ **Manuálně mapovat formuláře** na šablony přes admin rozhraní
- ✅ **Nebo použít HTML šablony** pro pokročilé customizace

## 📦 Instalace

1. Nahraj složku `bricks-email-templates` do `wp-content/plugins/`
2. Aktivuj plugin v administraci WordPress
3. Přejdi do **Bricks Emails** v hlavním menu
4. Podmenu **Formuláře a šablony** slouží k přiřazení šablon
5. Podmenu **Vytvořit vlastní šablonu** slouží k úpravě designu

## 🚀 Použití

### Metoda 1: Vizuální Email Builder (doporučeno)

#### 1. Vytvoř šablonu v Email Builderu

1. Přejdi do **Email Builder** v hlavním menu
2. Klikni na **Nová šablona**
3. Vyplň:
   - **Název šablony** (např. "Kontaktní formulář")
   - **Layout** - Vyber mezi Karta (výchozí), Kompaktní, Moderní, nebo Prostorný
   - **Barvy**:
     - Header Start & Konec (pro gradient)
     - Akcent (barva rámečku polí)
     - Pozadí
   - **Logo** (volitelné) - Nahraj logo pomocí media uploaderu
   - **Předmět emailu** - (volitelné) Přepíše předmět nastavený v Bricks
   - **Název e-mailu** (Nadpis v hlavičce, např. "Nová zpráva")
   - **Text emailu** (Úvodní text před poli)
   - **Text v patičce** (např. název webu)
4. Klikni **👁️ Náhled** pro zobrazení šablony
5. Klikni **💾 Uložit šablonu**

#### 2. Namapuj formulář na šablonu

1. Přejdi do **Bricks Emails → Formuláře a šablony**
2. Najdi svůj Bricks formulář v seznamu
3. Vyber šablonu z dropdown menu:
   - **Vizuální šablony** (vytvořené v builderu)
   - **HTML Soubory** (např. "Jednoduchá šablona" nebo vlastní soubory)
   - **Výchozí Bricks form HTML** (žádná šablona, použije se čistý Bricks výstup)
4. Klikni **Uložit přiřazení**

#### 3. Hotovo! 🎉

Když někdo odešle formulář, email bude automaticky použít tvou vizuální šablonu s vlastními barvami a layoutem.

---

### Metoda 2: HTML Šablony (pro pokročilé)

#### 1. Vytvoř formulář v Bricks

Vytvoř formulář normálně v Bricks Builderu s libovolnými fieldy.

### 2. Vytvoř email šablonu

Vytvoř nový `.php` soubor ve složce `templates/`, například `muj-formular.php`:

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial; background: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; }
        .header { background: #2563eb; color: white; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nová zpráva z webu</h1>
        </div>
        
        <!-- Automaticky zobrazí všechna pole -->
        {{all_fields}}
        
        <!-- Nebo použij konkrétní pole podle ID z Bricks -->
        <p><strong>Jméno:</strong> {{a28d3ad}}</p>
        <p><strong>Email:</strong> {{b39f2cd}}</p>
    </div>
</body>
</html>
```

### 3. Namapuj formulář na šablonu

1. Přejdi do **Nastavení → Email Templates**
2. Najdi svůj formulář v seznamu
3. Vyber šablonu z dropdown menu (File šablony jsou označené "(File)")
4. Klikni na **Uložit nastavení**

---

## 📝 Dostupné placeholdery

### `{{all_fields}}`
Automaticky zobrazí všechna pole z formuláře s pěkným formátováním.

### `{{field_id}}`
Zobraz konkrétní pole podle jeho ID z Bricks (např. `{{a28d3ad}}`).

### PHP funkce
Můžeš použít jakékoliv WordPress funkce v `.php` šablonách:

```php
<?php echo get_bloginfo('name'); ?>
<?php echo date('d.m.Y H:i:s'); ?>
<?php echo get_field('custom_field'); ?>
```

## 🎨 Layouty v Email Builderu

### Karta (Doporučeno)
- Čistý design s kulatými rohy
- Oddělené sekce pro hlavičku, obsah a patičku
- Profesionální vzhled

### Kompaktní
- Menší mezery (20px padding)
- Úsporný design
- Ideální pro jednoduché formuláře

### Moderní
- Vyvážené mezery
- Moderní vzhled
- Univerzální použití

### Prostorný
- Velké mezery (50px padding)
- Vzdušný design
- Ideální pro důležité formuláře

## 💡 Tipy

### Visual Builder
- **Barvy headeru**: Použij gradient pro profesionální vzhled
- **Logo**: Doporučená velikost max 150px šířka
- **Preview**: Vždy zkontroluj náhled před uložením
- **Layouty**: Zkus všechny 3 layouty a vyber ten nejlepší

### HTML Šablony
Pro emaily vždy používej inline CSS nebo `<style>` v `<head>`, ne externí CSS soubory.

### Testování
Otestuj šablonu odesláním testovacího formuláře a zkontroluj přijatý email.

### Více formulářů
Můžeš mít více formulářů používajících stejnou šablonu, nebo každý formulář může mít vlastní šablonu.

### Podmíněné zobrazení
```php
<?php if (isset($field_value)): ?>
    <p>Pole je vyplněno: <?php echo $field_value; ?></p>
<?php endif; ?>
```

## 🔧 Struktura pluginu

```
bricks-email-templates/
├── bricks-email-templates.php    # Hlavní soubor pluginu
├── templates/                     # Složka pro email šablony
│   ├── default.php               # Výchozí šablona
│   └── kontakt.php               # Příklad šablony
└── README.md                     # Tato dokumentace
```

## ❓ Časté otázky

**Q: Jak zjistím ID pole z Bricks?**  
A: V Bricks editoru klikni na pole a podívej se do nastavení. ID najdeš v sekci "Advanced" nebo ho můžeš vidět v dynamických datech jako `{{field_id}}`.

**Q: Můžu použít ACF pole v šabloně?**  
A: Ano! Šablona je běžný PHP soubor, takže můžeš použít `get_field()` a další WordPress funkce.

**Q: Co když nechci mapovat formulář na šablonu?**  
A: Formulář bude fungovat normálně s původním nastavením z Bricks.

**Q: Můžu mít různé šablony pro admin a uživatele?**  
A: Momentálně ne, ale můžeš to implementovat úpravou kódu v `process_email_template()` metodě.

## 📄 Licence

GPL v2 or later

## 👤 Autor

Vytvořeno pro snadnější práci s Bricks Builder formuláři.
