#!/usr/bin/env python3
"""Probe Athens LIII (284) cross-group theory vs aggregate standings."""

ga = {
    "Alkis P": (84, 18),
    "Ektoras K": (51, 37),
    "Panayotis P": (54, 43),
    "George Ka": (34, 35),
    "Filippos M": (28, 47),
    "Yakos T": (17, 47),
    "Kostas Ka": (22, 63),
}
gb = {
    "Spyros P": (77, 26),
    "Nikos A": (67, 22),
    "Vasilis K": (51, 43),
    "Stelios T": (43, 61),
    "Kostas O": (31, 49),
    "Vagelis D": (27, 59),
    "Nikos Al": (25, 61),
}

po_rows = [
    ("Spyros P", 10, 1),
    ("Panayotis P", 7, 3),
    ("Spyros P", 5, 1),
    ("Nikos A", 2, 7),
    ("George Ka", 5, 4),
    ("Panayotis P", 9, 12),
    ("Vasilis K", 4, 7),
    ("Stelios T", 1, 3),
    ("Stelios T", 0, 4),
    ("Ektoras K", 1, 6),
    ("Alkis P", 6, 7),
    ("Stelios T", 1, 3),
    ("Ektoras K", 7, 2),
    ("Alkis P", 6, 3),
    ("Nikos A", 4, 5),
    ("Vasilis K", 3, 4),
]

pl_rows = [
    ("Vagelis D", 1, 3),
    ("Yakos T", 3, 2),
    ("Kostas Ka", 2, 4),
    ("Yakos T", 2, 4),
    ("Vagelis D", 3, 2),
    ("Kostas O", 5, 0),
    ("Nikos Al", 1, 5),
    ("Nikos Al", 3, 4),
    ("Filippos M", 3, 3),
    ("Filippos M", 3, 4),
    ("Nikos Al", 5, 1),
    ("Kostas O", 4, 3),
    ("Vagelis D", 2, 1),
    ("Kostas Ka", 1, 1),
    ("Yakos T", 2, 3),
    ("Filippos M", 2, 5),
    ("Kostas Ka", 5, 2),
    ("Kostas O", 0, 2),
]

def agg(group, cross_rows, names):
    cross = {n: [0, 0] for n in names}
    for a, gf, ga_ in cross_rows:
        cross[a][0] += gf
        cross[a][1] += ga_
    rows = []
    for n in names:
        g = group[n]
        c = cross[n]
        tg, tga = g[0] + c[0], g[1] + c[1]
        rows.append((n, g, c, tg, tga, tg - tga))
    rows.sort(key=lambda r: (-r[5], -r[3]))
    return rows

top8 = [
    "Alkis P",
    "Ektoras K",
    "George Ka",
    "Nikos A",
    "Panayotis P",
    "Spyros P",
    "Stelios T",
    "Vasilis K",
]
bot6 = ["Filippos M", "Kostas Ka", "Kostas O", "Nikos Al", "Vagelis D", "Yakos T"]
group_all = {**ga, **gb}

print("=== Top-8: group + Playoffs Group aggregate ===")
for i, (n, g, c, tg, tga, gd) in enumerate(agg(group_all, po_rows, top8), 1):
    print(f"{i:2} {n:12} group {g[0]:3}-{g[1]:3}  cross {c[0]:2}-{c[1]:2}  agg {tg:3}-{tga:3} gd {gd:+4}")

print("\n=== Bottom-6: group + Playouts Group aggregate ===")
for i, (n, g, c, tg, tga, gd) in enumerate(agg(group_all, pl_rows, bot6), 9):
    print(f"{i:2} {n:12} group {g[0]:3}-{g[1]:3}  cross {c[0]:2}-{c[1]:2}  agg {tg:3}-{tga:3} gd {gd:+4}")

# Semifinalists from KO
print("\nSemifinalists (KO): Alkis P, Panayotis P, Spyros P, Nikos A")
print("Top-4 by aggregate (group+playoffs):", [r[0] for r in agg(group_all, po_rows, top8)[:4]])

# Check if playoffs-only ranks match semis
po_only = []
for n in top8:
    c = sum(gf for a, gf, ga_ in po_rows if a == n), sum(ga_ for a, gf, ga_ in po_rows if a == n)
    po_only.append((n, c[0] - c[1], c[0], c[1]))
po_only.sort(key=lambda r: (-r[1], -r[2]))
print("Playoffs-only order:", [r[0] for r in po_only])
