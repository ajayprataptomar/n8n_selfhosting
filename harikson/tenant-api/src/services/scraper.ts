import logger from '../utils/logger.js';
import axios from 'axios';
import * as cheerio from 'cheerio';

/**
 * Detects all HTTP/HTTPS URLs in a given text string.
 */
export function extractUrls(text: string): string[] {
  if (!text) return [];
  const urlRegex = /(https?:\/\/[^\s]+)/g;
  const matches = text.match(urlRegex);
  return matches ? [...new Set(matches)] : [];
}

/**
 * Fetches the HTML of the provided URLs, extracts clean text.
 */
export async function crawlUrls(urls: string[]): Promise<string> {
  if (!urls || urls.length === 0) return '';

  const results: string[] = [];

  for (const url of urls) {
    try {
      const response = await axios.get(url, { timeout: 10000 });
      const html = response.data;
      const $ = cheerio.load(html);

      // Remove unwanted tags
      $('script, style, noscript, iframe, svg, nav, footer, header').remove();

      const title = $('title').text().trim() || url;
      // Extract clean text
      const cleanText = $('body').text().replace(/\s+/g, ' ').trim();

      results.push(
        `URL: ${url}\nTitle: ${title}\nContent:\n${cleanText.substring(0, 5000)}...`
      );
    } catch (err: any) {
      logger.error(`Failed to crawl ${url}:`, err.message);
      results.push(`URL: ${url}\nError: Failed to crawl website.`);
    }
  }

  return results.join('\n\n');
}

/**
 * Scrapes DuckDuckGo HTML for top search results.
 */
export async function searchWeb(query: string): Promise<string> {
  if (!query) return '';
  try {
    const searchUrl = `https://html.duckduckgo.com/html/?q=${encodeURIComponent(query)}`;
    const response = await axios.get(searchUrl, {
      headers: {
        'User-Agent':
          'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
      },
      timeout: 10000,
    });

    const $ = cheerio.load(response.data);
    const results: string[] = [];

    $('.result').each((i, el) => {
      if (i >= 5) return false; // Get top 5 results
      const title = $(el).find('.result__title').text().trim();
      const snippet = $(el).find('.result__snippet').text().trim();
      const link = $(el).find('.result__url').attr('href');

      if (title && snippet) {
        results.push(`Title: ${title}\nSnippet: ${snippet}\nURL: ${link}`);
      }
    });

    if (results.length === 0) return 'No web search results found.';
    return results.join('\n\n');
  } catch (err: any) {
    logger.error(`Failed to search web for "${query}":`, err.message);
    return 'Web search failed. Proceed using existing knowledge.';
  }
}
