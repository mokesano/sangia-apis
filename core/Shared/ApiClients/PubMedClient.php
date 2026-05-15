<?php
declare(strict_types=1);

namespace Sangia\Core\Shared\ApiClients;

/**
 * PubMed / NCBI E-utilities client.
 * Set PUBMED_API_KEY in .env for higher rate limits (10 req/s vs 3 req/s).
 * Docs: https://www.ncbi.nlm.nih.gov/books/NBK25501/
 *
 * Coverage: biomedical and life sciences literature.
 * Cited-by is available only for PubMed Central (PMC) articles.
 */
class PubMedClient extends HttpClient
{
    private const BASE   = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils';
    private string $keyParam;

    public function __construct()
    {
        $key = $this->cfg('PUBMED_API_KEY');
        $this->keyParam = $key ? "&api_key=$key" : '';
    }

    /**
     * Resolves a DOI to a PubMed ID (PMID).
     * Returns null if the article is not in PubMed.
     */
    public function getPmidByDoi(string $doi): ?string
    {
        $url  = self::BASE . '/esearch.fcgi?db=pubmed&term=' . urlencode($doi . '[doi]')
              . '&retmode=json' . $this->keyParam;
        $data = $this->httpGet($url);
        $ids  = $data['esearchresult']['idlist'] ?? [];
        return $ids[0] ?? null;
    }

    /**
     * Returns article summary/metadata for a PMID.
     */
    public function getSummary(string $pmid): array
    {
        $url  = self::BASE . '/esummary.fcgi?db=pubmed&id=' . urlencode($pmid)
              . '&retmode=json' . $this->keyParam;
        $data = $this->httpGet($url);
        return $data['result'][$pmid] ?? [];
    }

    /**
     * Returns PubMed Central articles that cite the given PMID.
     * Note: coverage limited to PMC Open Access subset.
     */
    public function getCitedBy(string $pmid, int $limit = 100): array
    {
        $url  = self::BASE . '/elink.fcgi?dbfrom=pubmed&db=pubmed'
              . '&linkname=pubmed_pubmed_citedin&id=' . urlencode($pmid)
              . '&retmode=json' . $this->keyParam;
        $data = $this->httpGet($url);

        $linksets = $data['linksets'][0]['linksetdbs'] ?? [];
        $ids      = [];
        foreach ($linksets as $ls) {
            if (($ls['linkname'] ?? '') === 'pubmed_pubmed_citedin') {
                $ids = array_slice($ls['links'] ?? [], 0, $limit);
                break;
            }
        }

        if (empty($ids)) return [];

        // Batch-fetch summaries for the citing PMIDs
        $idStr = implode(',', $ids);
        $url2  = self::BASE . '/esummary.fcgi?db=pubmed&id=' . urlencode($idStr)
               . '&retmode=json' . $this->keyParam;
        $data2 = $this->httpGet($url2);
        $result = $data2['result'] ?? [];

        $citations = [];
        foreach ($ids as $id) {
            $item = $result[(string) $id] ?? [];
            if (empty($item)) continue;

            $doi = null;
            foreach ($item['articleids'] ?? [] as $aid) {
                if (($aid['idtype'] ?? '') === 'doi') {
                    $doi = $aid['value'] ?? null;
                    break;
                }
            }

            $citations[] = [
                'citing_doi'  => $doi,
                'pmid'        => (string) $id,
                'title'       => $item['title'] ?? null,
                'year'        => (int) substr($item['pubdate'] ?? '', 0, 4) ?: null,
                'authors'     => array_column($item['authors'] ?? [], 'name'),
                'source'      => 'pubmed',
            ];
        }

        return $citations;
    }

    /**
     * Full pipeline: DOI → PMID → cited-by list.
     * Returns empty array if article not in PubMed.
     */
    public function getCitationsByDoi(string $doi, int $limit = 100): array
    {
        $pmid = $this->getPmidByDoi($doi);
        if ($pmid === null) return [];
        return $this->getCitedBy($pmid, $limit);
    }
}
