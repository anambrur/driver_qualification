// Rich-text editor (Trix) for Platform > Content pages and blog posts. The HTML is sanitized
// server-side on save (App\Support\RichText).
import Trix from "trix";
import "trix/dist/trix.css";

// Pages have their own <h1>; the editor's heading button makes section headings
Trix.config.blockAttributes.heading1.tagName = "h2";

// No file or image attachments: covers are uploaded separately
document.addEventListener("trix-file-accept", (event) => event.preventDefault());
