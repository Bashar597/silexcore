# SilexCore Website — Asset Manifest

The HTML files reference specific image filenames in `assets/img/`. To make the site light up with your real photos, copy each file from your project storage to the matching path below.

You can rename your files to match — or keep your filenames and update the HTML once (search-replace). I'd recommend renaming since the new names are cleaner and more SEO-friendly.

## Folder structure on the website

```
silexcore/
├── index.html
├── (other pages — coming in phase 2/3)
└── assets/
    ├── css/style.css
    ├── js/main.js
    ├── img/
    │   ├── logo/
    │   │   ├── silexcore-logo.svg          (the wordmark logo - we may regenerate as SVG)
    │   │   ├── silexcore-logo-white.png    (white version for dark sections)
    │   │   └── favicon.svg                  (browser tab icon)
    │   │
    │   ├── products/                        (clean product-on-white shots)
    │   │   ├── conductive-paint-main.webp
    │   │   ├── conductive-paint-2.webp
    │   │   ├── conductive-paint-3.webp
    │   │   ├── conductive-paint-tester.webp
    │   │   ├── conductive-paint-printing.webp
    │   │   ├── premium-paint-main.webp
    │   │   ├── conductive-silicone-main.webp
    │   │   ├── conductive-silicone-2.webp
    │   │   ├── conductive-silicone-3.webp
    │   │   ├── conductive-glue-main.webp
    │   │   └── conductive-glue-2.webp
    │   │
    │   └── lifestyle/                       (in-use / application shots)
    │       ├── hero-paint-circuit.webp
    │       ├── paper-circuit-led.webp
    │       ├── emi-shielding-enclosure.webp
    │       ├── remote-button-repair.webp
    │       ├── solder-free-repair.webp
    │       ├── guitar-cavity-shielding.webp
    │       └── flexible-circuit.webp
    │
    └── pdf/                                 (downloadable resources)
        ├── silexcore-conductive-paint-sds.pdf
        ├── silexcore-premium-conductive-paint-sds.pdf
        ├── silexcore-conductive-silicone-sds.pdf
        ├── silexcore-conductive-glue-sds.pdf
        ├── silexcore-conductive-paint-usage.pdf
        ├── silexcore-premium-conductive-paint-usage.pdf
        ├── silexcore-conductive-silicone-usage.pdf
        └── silexcore-conductive-glue-usage.pdf
```

## Mapping: your file → website file

### Products (clean shots, the ones we want)

| Your file in project | Copy to | Used on |
|---|---|---|
| `ConductivePaint30mlmain1Amazon.png` | `assets/img/products/conductive-paint-main.webp` | Homepage card · Product page hero |
| `ConductivePaint30mlMainAmazon.png` | `assets/img/products/conductive-paint-2.webp` | Product page gallery |
| `ConductivePaint30ml2Amazon.png` | `assets/img/products/conductive-paint-3.webp` | Product page gallery |
| `ConductivePaint30ml3Amazon.png` | `assets/img/products/conductive-paint-4.webp` | Product page gallery |
| `ConductivePainttesterAmazon.png` | `assets/img/products/conductive-paint-tester.webp` | Product page · conductivity demo |
| `ConductivePaintprintingCellAmazon.png` | `assets/img/products/conductive-paint-printing.webp` | Product page · screen printing demo |
| `silexcorepremiumconductivepaintemfshieldingcircuitrepair.webp` | `assets/img/products/premium-paint-main.webp` | Homepage card · Premium product page hero |
| `silexcoreconductivesiliconewaterresistantelectronics.webp` | `assets/img/products/conductive-silicone-main.webp` | Homepage card · Silicone product page hero |
| `conductivesiliconeelectricalconductivity535ohmsq.webp` | `assets/img/products/conductive-silicone-2.webp` | Silicone product page gallery |
| `silexcoreconductivesiliconecompatiblesurfaces.webp` | `assets/img/products/conductive-silicone-3.webp` | Silicone product page gallery |
| **TBD — needs reshoot** | `assets/img/products/conductive-glue-main.webp` | Homepage card · Glue product page hero |

### Lifestyle / application shots

| Your file in project | Copy to | Used on |
|---|---|---|
| `drawelectroniccircuitssilexcoreconductivepaint.webp` | `assets/img/lifestyle/hero-paint-circuit.webp` | **Homepage hero background** |
| `drawelectroniccircuitssilexcoreconductivepaint.webp` | `assets/img/lifestyle/paper-circuit-led.webp` | Gallery tile 1 (can be same as above or different angle) |
| `silexcoreconductivepaintemfemishielding.webp` | `assets/img/lifestyle/emi-shielding-enclosure.webp` | Gallery tile 2 |
| `conductivesiliconeremoterepair.png` | `assets/img/lifestyle/remote-button-repair.webp` | Gallery tile 3 |
| `silexcoreconductivegluesolarcellrepairsolderfree.webp` | `assets/img/lifestyle/solder-free-repair.webp` | Gallery tile 4 |
| `silexcoreconductivepaintguitaremfshielding.webp` | `assets/img/lifestyle/guitar-cavity-shielding.webp` | Gallery tile 5 |
| `conductivesiliconecircuitprototypingwithoutacidetching.webp` | `assets/img/lifestyle/flexible-circuit.webp` | Gallery tile 6 |
| `silexcoreconductiveglueparkingsensorcamerarepair.webp` | `assets/img/lifestyle/glue-parking-sensor.webp` | Glue product page |
| `silexcoreconductivegluefeaturesinfographicsolderfree.webp` | ⚠️ **DO NOT USE** | (AI-generated, garbled text — flagged in audit) |
| `silexcorepremiumconductivepaintemfshieldingcircuitrepair.webp` | already mapped above | — |

### Logos

| Your file | Copy to | Used on |
|---|---|---|
| `logo.png` | `assets/img/logo/silexcore-logo.png` | Reference / archive — current site rebuilds the wordmark as inline SVG so it scales crisply at all sizes |
| `Logo__LargeSize.png` | `assets/img/logo/silexcore-logo-large.png` | High-res archive for print and packaging |

*Note: I rebuilt the logo as inline SVG in every HTML page rather than referencing a PNG. This means it stays crisp at any size, renders fast, and works in both light and dark modes. The yellow swoosh recreation in the SVG is close to your logo but not pixel-identical. If you want it to match your original logo exactly, share a vector version (SVG/AI/PDF) and I'll trace it precisely.*

### PDFs (Safety Data Sheets + usage guides)

| Your file | Copy to |
|---|---|
| `silexcore_conductive_paint_sds.pdf` | `assets/pdf/silexcore-conductive-paint-sds.pdf` |
| `silexcore_premium_conductive_paint_sds.pdf` | `assets/pdf/silexcore-premium-conductive-paint-sds.pdf` |
| `silexcore_silicone_sds.pdf` | `assets/pdf/silexcore-conductive-silicone-sds.pdf` |
| `silexcore_conductive_glue_sds.pdf` | `assets/pdf/silexcore-conductive-glue-sds.pdf` |
| `silexcore__premium_conductive_paint_usage_instructions_and_guidelines.pdf` | `assets/pdf/silexcore-premium-conductive-paint-usage.pdf` |
| `silexcore__conductive_silicone_usage_instructions_and_guidelines.pdf` | `assets/pdf/silexcore-conductive-silicone-usage.pdf` |
| `silexcore_conductive_glue_usage_instructions_and_guidelines.pdf` | `assets/pdf/silexcore-conductive-glue-usage.pdf` |

*Missing: usage guide for regular Conductive Paint (SC-650CP). The other three exist in your project but I didn't see this one — let me know if it exists.*

## Until you copy assets

The site will work with broken image icons or empty boxes where pictures would be. Every `<img>` tag has an `onerror` fallback that hides or dims the empty slot rather than showing a broken-image icon, so the layout stays clean even with no photos. As you drop files in, they appear one by one.

## Videos

Per your earlier note, I'll add relevant placeholder videos when I build the product detail pages in Phase 2. Each product page will have a `<video>` slot pointing at `assets/img/products/conductive-paint-demo.mp4` (and similar) — easy to swap.

---

**One reminder from the audit:** I flagged the conductive glue AI-generated images as unusable. The site references a `conductive-glue-main.webp` that doesn't have a clean source in your current files. Until you reshoot, the glue card will show an empty slot. Options:
1. Use one of your less-doctored glue shots if you have one
2. Use the SilexCore Conductive Glue bottle photo from the SDS sheet header (small, but real)
3. Live with an empty card until you reshoot
