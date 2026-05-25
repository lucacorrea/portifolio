import React from "react";
import type { Metadata } from "next";
import { Analytics } from "@vercel/analytics/next";
import "./globals.css";

export const metadata: Metadata = {
  title: "L&J Soluções Tecnológicas | Portfólio Digital",
  description:
    "Portfólio da L&J Soluções Tecnológicas: sites, sistemas web, SaaS, automação, identidade visual e drone para empresas.",
  generator: "Next.js",
  metadataBase: new URL("https://ljsolucoestech.com.br"),
  openGraph: {
    title: "L&J Soluções Tecnológicas | Portfólio Digital",
    description:
      "Sites, sistemas web, SaaS, automação, identidade visual e drone com base técnica segura e UX profissional.",
    url: "https://ljsolucoestech.com.br",
    siteName: "L&J Soluções Tecnológicas",
    locale: "pt_BR",
    type: "website",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="pt-BR">
      <body className="font-sans antialiased">
        {children}
        <Analytics />
      </body>
    </html>
  );
}
