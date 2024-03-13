<?php

namespace Rafik\PscWorldWebservice;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr18Adapter\Soap\SoapPsr18Client;
use Rafik\PscWorldWebservice\Exception\CertificateNotFoundException;
use Rafik\PscWorldWebservice\Exception\DuplicatedIdException;
use Rafik\PscWorldWebservice\Exception\HashNotProvidedException;
use Rafik\PscWorldWebservice\Exception\IdNotProvidedException;
use Rafik\PscWorldWebservice\Exception\InactiveAccountException;
use Rafik\PscWorldWebservice\Exception\InsufficientFundsException;
use Rafik\PscWorldWebservice\Exception\InvalidCertificateException;
use Rafik\PscWorldWebservice\Exception\InvalidOidException;
use Rafik\PscWorldWebservice\Exception\PscWorldExceptionInterface;
use Rafik\PscWorldWebservice\Exception\UnknownHashException;
use Rafik\PscWorldWebservice\Exception\InvalidCredentialsException;
use Rafik\PscWorldWebservice\Exception\PasswordNotProvidedException;
use Rafik\PscWorldWebservice\Exception\UserNotProvidedException;
use Rafik\PscWorldWebservice\Model\Certificate;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use vakata\asn1\structures\TimestampResponse;

class PscWorldClient
{
    private Serializer $serializer;

    private \SoapClient $client;

    private string $username;

    private string $password;

    /**
     * @throws \SoapFault
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $username,
        string $password,
    ) {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $propertyTypeExtractor = new ReflectionExtractor();

        $this->serializer = new Serializer(
            [new ObjectNormalizer($classMetadataFactory, $nameConverter, null, $propertyTypeExtractor)],
            [new XmlEncoder()],
        );
        $this->client = new SoapPsr18Client(
            $httpClient,
            $requestFactory,
            $streamFactory,
            'https://nomtsclient.pscworld.com/NOMTS_Client.svc?wsdl',
            ['soap_version' => SOAP_1_1],
        );
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @throws PscWorldExceptionInterface
     */
    public function generate(string $id, StreamInterface $data): string
    {
        $hash = $this->generateHash($data);
		$base64Hash = base64_encode($hash);

        $request = $this->createRequest([
            'hash' => $base64Hash,
            'identificador' => $id,
        ]);
        $response = $this->client->__soapCall('Genera', $request);

        return $this->parseResponse($response->GeneraResult);
    }

    /**
     * @throws PscWorldExceptionInterface
     */
    public function recover(string $id): string
    {
        $request = $this->createRequest([
            'identificador' => $id,
        ]);
        $response = $this->client->__soapCall('Recupera', $request);

        return $this->parseResponse($response->RecuperaResult);
    }

    /**
     * @throws PscWorldExceptionInterface
     */
    public function validate(string $certificate): Certificate
    {
        $request = $this->createRequest([
            'constancia' => $certificate,
        ]);
        $response = $this->client->__soapCall('ValidaConstancia', $request);

        $xml = $this->parseResponse($response->ValidaConstanciaResult);

        return $this->serializer->deserialize($xml, Certificate::class, 'xml');
    }

	public function validateData(string $base64Certificate, StreamInterface $data): bool
	{
		$certificate = base64_decode($base64Certificate);
		$fileHash = $this->generateHash($data);

		$timestampResponse = TimestampResponse::fromString($certificate)->toArray();
		$token = $timestampResponse['timeStampToken']['signedData']['tokenInfo'];
		$certificateHash = base64_decode($token['data']['messageImprint']['hashedMessage']);

		return hash_equals($certificateHash, $fileHash);
	}

    private function createRequest(array $params): array
    {
        $request = $this->withAuthentication($params);

        return [$request];
    }

    /**
     * @throws PscWorldExceptionInterface
     */
    private function parseResponse(string $data): string
    {
        if (str_starts_with($data, 'ERROR: ')) {
            $error = substr($data, 7);

            throw match($error) {
                'USUARIO_NO_PROPORCIONADO' => new UserNotProvidedException('The user was not provided'),
                'PASSWORD_NO_PROPORCIONADO' => new PasswordNotProvidedException('The password was not provided'),
                'USUARIO_INCORRECTO' => new InvalidCredentialsException(sprintf('The username %s is invalid', $this->username)),
                'PASSWORD_INCORRECTO' => new InvalidCredentialsException(sprintf('The password %s is invalid', $this->password)),
                'ERROR_USUARIO_NO_EXISTENTE' => new InvalidCredentialsException('The username and password does not exist'),
                'ID_NO_PROPORCIONADO' => new IdNotProvidedException('The id was not provided'),
                'BASE64_NO_PROPORCIONADO' => new HashNotProvidedException('The hash was not provided'),
                'HASH_NO_ES_SHA256' => new UnknownHashException('The provided hash is not SHA-256'),
                'IDENTIFICADOR_EXISTENTE' => new DuplicatedIdException('The provided id is duplicated'),
                'EMPRESA_NO_ACTIVA' => new InactiveAccountException('The account is inactive'),
                'EMPRESA_SIN_SALDO' => new InsufficientFundsException('The account does not have funds'),
                'CERTIFICADO_INCORRECTO' => new InvalidCertificateException('The certificate is not signed by PSC World or his providers'),
                'CONSTANCIA_NO_ENCONTRADA' => new CertificateNotFoundException('The certificate can not be found'),
                'OID_INCORRECTO' => new InvalidOidException('The certificate is not signed by PSC World or his providers'),
            };
        }

        return $data;
    }

	private function generateHash(StreamInterface $data): string
	{
		$hash = hash_init('sha256');

		if ($data->isSeekable()) {
			$data->rewind();
		}

		while (!$data->eof()) {
			$buffer = $data->read(8192);

			hash_update($hash, $buffer);
		}

		return hash_final($hash, true);
	}

    private function withAuthentication(array $request): array
    {
        $request = [...$request];

        $request['usuario'] = $this->username;
        $request['password'] = $this->password;

        return $request;
    }
}