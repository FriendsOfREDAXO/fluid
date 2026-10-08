/*
 * Ersatz für Studio _shadow.js: Ebene für Menüs und Dialoge der Formatierungsleiste.
 * Auf der Website setzt der Editor seine Shadow-DOM-Ebene (BlocksRich.setLayer), im Backend bleibt alles im Dokument.
 */
let box = null;
export const setLayer = (el) => { box = el; };
export const layerBox = () => box || document.body;
